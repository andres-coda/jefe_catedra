<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Controllers\CursoController;
use App\Core\AuthMiddleware;
use App\Core\Database;
use App\Core\Http;
use App\Core\PermissionDeniedException;
use App\Core\PermissionMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\SchoolContextMiddleware;
use App\Models\Area;
use App\Models\Curso;
use App\Models\CursoEscuela;
use App\Models\CursoEscuelaDia;
use App\Models\Dia;
use App\Models\Materia;
use Tests\Support\DbCase;

/**
 * Flujo completo de alta de curso dictado (formularios-autocomplete, WU2b).
 * El despachador replica index.php (AuthMiddleware + SchoolContextMiddleware +
 * rutas reales + manejo de PermissionDeniedException) y los tests recorren
 * REQ-21..REQ-27 con RLS real. Naming español por escenario. Sin JS: los
 * autocompletar degradan a texto libre (id_* vacíos) y el servidor decide.
 */
final class CursoCrearE2ETest extends DbCase
{
    public function testJefeRecibe403YAnonimoRedirectAlLogin(): void
    {
        $this->usarComo(self::$jefeId);
        self::assertSame(403, $this->despachar('GET', '/escuelas/' . self::$escuelaId . '/cursos/nuevo')->statusCode(), 'Jefe: GET nuevo → 403 (REQ-21).');
        self::assertSame(403, $this->despachar('POST', '/escuelas/' . self::$escuelaId . '/cursos', $this->postValida())->statusCode(), 'Jefe: POST → 403.');

        $this->anonimo();
        self::assertSame(302, $this->despachar('GET', '/escuelas/' . self::$escuelaId . '/cursos/nuevo')->statusCode(), 'Anónimo: redirect a /login.');
    }

    public function testDirectivoVeElFormularioConAutocompletarYHorarios(): void
    {
        $this->usarComo(self::$directivoId);

        $respuesta = $this->despachar('GET', '/escuelas/' . self::$escuelaId . '/cursos/nuevo');
        self::assertSame(200, $respuesta->statusCode());
        $html = self::cuerpo($respuesta);
        self::assertStringContainsString('Nuevo curso', $html, 'Título del formulario (REQ-21).');
        self::assertStringContainsString('data-autocompletar="area"', $html);
        self::assertStringContainsString('data-autocompletar="materia"', $html);
        self::assertStringContainsString('data-autocompletar="curso"', $html);
        self::assertStringContainsString('id="id_materia"', $html, 'Hidden id_{recurso} presente (D7).');
        self::assertStringContainsString('name="dia[]"', $html, 'Filas de horario (REQ-23).');
    }

    public function testDirectivoCreaCursoConNombresNuevosYRedirigeAFicha(): void
    {
        $this->usarComo(self::$directivoId);

        $lunes = (string) $this->diaId('lunes');
        $martes = (string) $this->diaId('martes');

        $post = $this->postValida([
            'area_nombre' => 'P4T Nueva Area',
            'materia_nombre' => 'P4T Nueva Mat',
            'curso_nombre' => 'P4T Nuevo Curso',
            'carga_horaria' => '4.5',
            'anio' => '',
            'dia' => [$lunes, $martes],
            'hora_entrada' => ['08:00', '14:00'],
            'hora_salida' => ['12:00', '18:00'],
        ]);

        $respuesta = $this->despachar('POST', '/escuelas/' . self::$escuelaId . '/cursos', $post);
        self::assertSame(302, $respuesta->statusCode(), 'Alta válida → PRG (REQ-25).');

        // Catálogos creados on-submit (REQ-22).
        self::assertNotSame([], Area::searchByNombre('P4T Nueva Area'), 'Área creada on-submit.');
        self::assertNotSame([], Materia::searchByNombre('P4T Nueva Mat'), 'Materia creada con su área.');
        self::assertNotSame([], Curso::searchByNombre('P4T Nuevo Curso'), 'Curso creado.');

        // El curso dictado existe con anio por defecto (año actual) y horarios.
        $curso = Curso::searchByNombre('P4T Nuevo Curso')[0];
        $dictado = CursoEscuela::listByEscuela(self::$escuelaId);
        $dictado = array_values(array_filter($dictado, static function (array $fila) use ($curso): bool {
            return (string) $fila['id_curso'] === (string) $curso['id'];
        }));
        self::assertCount(1, $dictado, 'Un único curso dictado para el cuarteto nuevo.');
        self::assertSame((int) date('Y'), (int) $dictado[0]['anio'], 'anio vacío → año actual (uq_ce_materia lo exige).');

        $horarios = CursoEscuelaDia::listByCursoEscuela((string) $dictado[0]['id']);
        self::assertCount(2, $horarios, 'Dos filas de horario persistidas (REQ-23/REQ-24).');

        // PRG: la ficha muestra el flash de éxito y el curso creado.
        $ficha = $this->despachar('GET', '/escuelas/' . self::$escuelaId . '/cursos/' . $dictado[0]['id']);
        self::assertSame(200, $ficha->statusCode());
        $html = self::cuerpo($ficha);
        self::assertStringContainsString('Curso creado correctamente.', $html, 'Flash de éxito (REQ-25).');
        self::assertStringContainsString('P4T Nuevo Curso', $html);
    }

    public function testHorarioConSalidaAnteriorReRendersizaConservandoValores(): void
    {
        $this->usarComo(self::$directivoId);

        $post = $this->postValida([
            'curso_nombre' => 'P4T Horario Curso',
            'dia' => [(string) $this->diaId('lunes')],
            'hora_entrada' => ['18:00'],
            'hora_salida' => ['08:00'],
        ]);

        $respuesta = $this->despachar('POST', '/escuelas/' . self::$escuelaId . '/cursos', $post);
        self::assertSame(200, $respuesta->statusCode(), 'Error → re-render, no redirect (REQ-27).');

        $html = self::cuerpo($respuesta);
        self::assertStringContainsString('La hora de entrada no puede ser posterior a la de salida.', $html);
        self::assertStringContainsString('is-invalida', $html, 'Marcador de campo inválido.');
        self::assertStringContainsString('value="18:00"', $html, 'El valor enviado se conserva en el re-render.');
        self::assertSame([], Curso::searchByNombre('P4T Horario Curso'), 'Sin insert: la validación corre antes de la transacción.');
    }

    public function testDiaRepetidoMuestraErrorAmigableSinInsertar(): void
    {
        $this->usarComo(self::$directivoId);

        $post = $this->postValida([
            'curso_nombre' => 'P4T Fila Curso',
            'dia' => [(string) $this->diaId('lunes'), (string) $this->diaId('lunes')],
            'hora_entrada' => ['08:00', '14:00'],
            'hora_salida' => ['12:00', '18:00'],
        ]);

        $respuesta = $this->despachar('POST', '/escuelas/' . self::$escuelaId . '/cursos', $post);
        self::assertSame(200, $respuesta->statusCode());
        $html = self::cuerpo($respuesta);
        self::assertStringContainsString('un día solo admite un horario', $html, 'Mensaje amigable (REQ-24).');
        self::assertStringContainsString('is-invalida', $html);
        self::assertSame([], Curso::searchByNombre('P4T Fila Curso'), 'Sin curso insertado.');
    }

    public function testMateriaForjadaNoInsertaNiCreaCatalogos(): void
    {
        $this->usarComo(self::$directivoId);

        // UUID v4 bien formado pero inexistente: el hidden no se descarta
        // (leerUuid) y la re-validación server-side lo rechaza (REQ-26/S7).
        $post = $this->postValida([
            'id_area' => self::$areaId,
            'area_nombre' => 'P4T Forjado Area',
            'id_materia' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'materia_nombre' => 'P4T Forjado Mat',
            'curso_nombre' => 'P4T Forjado Curso',
        ]);

        $respuesta = $this->despachar('POST', '/escuelas/' . self::$escuelaId . '/cursos', $post);
        self::assertSame(200, $respuesta->statusCode());
        $html = self::cuerpo($respuesta);
        self::assertStringContainsString('La materia seleccionada no existe.', $html, 'UUID inexistente → mensaje (REQ-26).');
        self::assertStringContainsString('is-invalida', $html);
        self::assertSame([], Curso::searchByNombre('P4T Forjado Curso'), 'Sin curso creado.');
        self::assertSame([], Materia::searchByNombre('P4T Forjado Mat'), 'Sin materia forjada.');
        self::assertSame([], Area::searchByNombre('P4T Forjado Area'), 'Sin área forjada.');
    }

    public function testMateriaDeOtraAreaSeRechaza(): void
    {
        $this->usarComo(self::$directivoId);

        // Materia del fixture (área P4T Area) con un área distinta forjada.
        $post = $this->postValida([
            'id_area' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'id_materia' => self::$materiaId,
            'materia_nombre' => 'P4T Mat',
            'curso_nombre' => 'P4T Alcance Curso',
        ]);

        $respuesta = $this->despachar('POST', '/escuelas/' . self::$escuelaId . '/cursos', $post);
        self::assertSame(200, $respuesta->statusCode());
        $html = self::cuerpo($respuesta);
        self::assertStringContainsString('La materia seleccionada no pertenece al área elegida.', $html, 'Alcance materia↔área (D7).');
        self::assertSame([], Curso::searchByNombre('P4T Alcance Curso'), 'Sin curso insertado.');
    }

    /**
     * POST /escuelas/{id}/cursos válido (base); $cambios pisa campos a medida.
     *
     * @param array<string, mixed> $cambios
     *
     * @return array<string, mixed>
     */
    private function postValida(array $cambios = []): array
    {
        $base = [
            'id_area' => '',
            'area_nombre' => '',
            'id_materia' => '',
            'materia_nombre' => 'P4T Mat',
            'id_curso' => '',
            'curso_nombre' => 'P4T1A',
            'carga_horaria' => '',
            'anio' => '2026',
            'dia' => [
                (string) $this->diaId('miercoles'),
            ],
            'hora_entrada' => ['09:00'],
            'hora_salida' => ['13:00'],
        ];

        return array_merge($base, $cambios);
    }

    private function diaId(string $nombre): ?string
    {
        foreach (Dia::list() as $dia) {
            if ($dia['nombre'] === $nombre) {
                return (string) $dia['id'];
            }
        }

        return null;
    }

    /**
     * Despacha como index.php: cadena de middlewares, rutas de alta de curso
     * dictado y manejo de PermissionDeniedException del front controller.
     *
     * @param array<string, mixed> $post
     */
    private function despachar(string $method, string $path, array $post = []): Response
    {
        $router = new Router([
            new AuthMiddleware(),
            new SchoolContextMiddleware('id'),
        ]);
        $cursos = new CursoController();

        $router->get('/escuelas/{id}/cursos', fn (Request $r): Response => $cursos->index($r));
        $router->get('/escuelas/{id}/cursos/{courseId}', fn (Request $r): Response => $cursos->show($r));
        $router->get('/escuelas/{id}/cursos/nuevo', function (Request $r) use ($cursos): Response {
            PermissionMiddleware::assert('directivo', 'curso_escuela.nuevo', $r);
            return $cursos->nuevo($r);
        });
        $router->post('/escuelas/{id}/cursos', function (Request $r) use ($cursos): Response {
            PermissionMiddleware::assert('directivo', 'curso_escuela.create', $r);
            return $cursos->crear($r);
        });

        try {
            return $router->dispatch(new Request($method, $path, [], $post));
        } catch (PermissionDeniedException $exception) {
            if ($exception->shouldRedirectToLogin()) {
                return Response::redirect(Http::url('login'));
            }

            return (new Response())->status(403)->body('Access denied.');
        }
    }

    private static function cuerpo(Response $response): string
    {
        ob_start();
        $response->send();

        return (string) ob_get_clean();
    }
}