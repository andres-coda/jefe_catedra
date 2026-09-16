<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Core\Request;
use App\Core\Response;
use App\Controllers\HomeController;
use App\Models\Escuela;
use App\Models\EscuelaUsuario;
use App\Models\Tarea;
use PDOException;
use Tests\Support\DbCase;

/**
 * Escenarios de aceptación (spec D3) con contexto RLS REAL por rol:
 *  - jefe administra tareas de su curso;
 *  - user y visitante son denegados (SQLSTATE 42501);
 *  - roles por email desde directivo;
 *  - favoritos alternan para el propio usuario;
 *  - la home página el catálogo para visitantes.
 */
final class ChecklistE2ETest extends DbCase
{
    public function testJefeAdministraTareasDeSuCurso(): void
    {
        $this->usarComo(self::$jefeId);

        $creada = Tarea::create(self::$cursoEscuelaId, 'P4T checklist', 'primera');
        $leida = Tarea::findById($creada['id']);
        self::assertNotNull($leida);
        self::assertSame('P4T checklist', $leida['descripcion']);

        Tarea::actualizarRealizado($creada['id'], true);
        self::assertTrue(self::esVerdadero(Tarea::findById($creada['id'])['realizado']));

        $lista = Tarea::listByCurso(self::$cursoEscuelaId);
        self::assertNotEmpty($lista);
        self::assertContains($creada['id'], array_column($lista, 'id'));
    }

    public function testUserNoPuedeCrearTareas(): void
    {
        $this->usarComo(self::$regularId);

        try {
            Tarea::create(self::$cursoEscuelaId, 'P4T prohibida', null);
            self::fail('RLS: un user sin pivot no puede insertar tareas del curso.');
        } catch (PDOException $exception) {
            self::assertSame('42501', $exception->getCode(), 'RLS WITH CHECK violado = SQLSTATE 42501.');
        }
    }

    public function testVisitanteNoPuedeCrearTareas(): void
    {
        $this->anonimo();

        try {
            Tarea::create(self::$cursoEscuelaId, 'P4T visitante', null);
            self::fail('RLS: un visitante no puede insertar tareas.');
        } catch (PDOException $exception) {
            self::assertSame('42501', $exception->getCode());
        }
    }

    public function testRolPorEmailAsignaYLee(): void
    {
        $this->usarComo(self::$directivoId);

        $asignado = EscuelaUsuario::assignByEmail(self::$escuelaId, 'p4t.regular@p4t.test', 'directivo');
        self::assertNotNull($asignado);
        self::assertSame('directivo', EscuelaUsuario::getRol(self::$escuelaId, self::$regularId));

        self::assertNull(EscuelaUsuario::assignByEmail(self::$escuelaId, 'no-existe@p4t.test', 'directivo'));
    }

    public function testFavoritosAlternanDosVeces(): void
    {
        $this->usarComo(self::$jefeId);

        self::assertTrue(\App\Models\EscuelaFavorita::toggle(self::$escuelaId, self::$jefeId));
        self::assertFalse(\App\Models\EscuelaFavorita::toggle(self::$escuelaId, self::$jefeId));
        self::assertFalse(\App\Models\EscuelaFavorita::isFavorita(self::$escuelaId, self::$jefeId));
    }

    public function testHomePageaCatalogoParaVisitante(): void
    {
        $this->anonimo();

        self::assertGreaterThanOrEqual(13, Escuela::count());
        self::assertCount(12, Escuela::paginar(0, 12));

        $request = new Request('GET', '/', ['page' => '2']);
        $response = (new HomeController())->home($request);

        self::assertSame(200, $response->statusCode());
        $html = self::cuerpo($response);
        self::assertStringContainsString('Página 2 de', $html, 'El paginador muestra la página actual y el total.');
    }

    private static function cuerpo(Response $response): string
    {
        ob_start();
        $response->send();
        return (string) ob_get_clean();
    }

    private static function esVerdadero($valor): bool
    {
        return in_array($valor, [true, 1, '1', 't', 'true'], true);
    }
}