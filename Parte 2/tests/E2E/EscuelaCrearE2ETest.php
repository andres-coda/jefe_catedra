<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Controllers\EscuelaController;
use App\Core\AuthMiddleware;
use App\Core\Database;
use App\Core\Http;
use App\Core\PermissionDeniedException;
use App\Core\PermissionMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\SchoolContextMiddleware;
use App\Models\Escuela;
use App\Models\Orientacion;
use App\Models\Turno;
use Tests\Support\DbCase;

/**
 * Flujo completo de alta de escuela (formularios-autocomplete, WU2). El
 * despachador replica index.php (AuthMiddleware + SchoolContextMiddleware +
 * rutas reales + manejo de PermissionDeniedException) y los tests recorren
 * REQ-11..REQ-17 con RLS real. Naming español por escenario.
 */
final class EscuelaCrearE2ETest extends DbCase
{
    public function testJefeYDirectivoReciben403YAnonimoRedirectAlLogin(): void
    {
        $this->usarComo(self::$jefeId);
        self::assertSame(403, $this->despachar('GET', '/escuelas/nueva')->statusCode(), 'Jefe: GET /escuelas/nueva → 403 (REQ-11).');
        self::assertSame(403, $this->despachar('POST', '/escuelas')->statusCode(), 'Jefe: POST /escuelas → 403.');

        $this->usarComo(self::$directivoId);
        self::assertSame(403, $this->despachar('GET', '/escuelas/nueva')->statusCode(), 'Directivo: 403 sin ser admin global.');

        $this->anonimo();
        self::assertSame(302, $this->despachar('GET', '/escuelas/nueva')->statusCode(), 'Anónimo: redirect a /login.');
    }

    public function testAdminCreaEscuelaConChipsDuplicadosYRedirigeAFicha(): void
    {
        $this->usarComo(self::$adminId);

        $respuesta = $this->despachar('POST', '/escuelas', $this->postValida(['nombre' => 'P4T Escuela Nueva']));
        self::assertSame(302, $respuesta->statusCode(), 'Alta válida → PRG (REQ-17).');

        // Los chips duplicados se deduplican: turno y orientación entran 1 vez.
        $filas = Escuela::searchByNombre('P4T Escuela Nueva');
        self::assertNotSame([], $filas, 'La escuela debe existir.');
        $escuelaId = (string) $filas[0]['id'];
        self::assertSame(1, self::conteoPivotes($escuelaId, 'escuela_turno'), 'Dedup de turnos (REQ-13).');
        self::assertSame(1, self::conteoPivotes($escuelaId, 'escuela_orientacion'), 'Dedup de orientaciones.');

        // PRG: la ficha muestra el flash de éxito y el nombre creado.
        $ficha = $this->despachar('GET', '/escuelas/' . $escuelaId);
        self::assertSame(200, $ficha->statusCode());
        $html = self::cuerpo($ficha);
        self::assertStringContainsString('Escuela creada correctamente.', $html, 'Flash de éxito (REQ-17).');
        self::assertStringContainsString('P4T Escuela Nueva', $html);

        self::assertNotSame([], Turno::searchByNombre('Mañana'), 'Turno creado on-submit (REQ-13).');
        self::assertNotSame([], Orientacion::searchByNombre('Artes'), 'Orientación creada on-submit.');
    }

    public function testCodigoPostalInvalidoReRendersizaConValoresYMarcador(): void
    {
        $this->usarComo(self::$adminId);

        $respuesta = $this->despachar('POST', '/escuelas', $this->postValida([
            'nombre' => 'P4T Escuela CP',
            'codigo_postal' => '123',
        ]));
        self::assertSame(200, $respuesta->statusCode(), 'Error → re-render, no redirect (REQ-12).');

        $html = self::cuerpo($respuesta);
        self::assertStringContainsString('El código postal debe tener entre 4 y 5 dígitos.', $html);
        self::assertStringContainsString('is-invalida', $html, 'Marcador de campo inválido.');
        self::assertStringContainsString('value="P4T Escuela CP"', $html, 'El valor enviado se conserva en el re-render.');
        self::assertSame([], Escuela::searchByNombre('P4T Escuela CP'), 'Sin insert: la validación corre antes de la transacción.');
    }

    public function testTurnoInvalidoMuestraErrorAmigableSinInsertar(): void
    {
        $this->usarComo(self::$adminId);

        $post = $this->postValida(['nombre' => 'P4T Escuela Turno']);
        $post['turno_nombres'] = ['Temprano'];

        $respuesta = $this->despachar('POST', '/escuelas', $post);
        self::assertSame(200, $respuesta->statusCode());
        $html = self::cuerpo($respuesta);
        self::assertStringContainsString('el turno debe ser Mañana, Tarde o Noche', $html, 'Mensaje amigable (REQ-15).');
        self::assertStringContainsString('is-invalida', $html);
        self::assertStringContainsString('value="Temprano"', $html, 'El turno inválido vuelve al input editable.');
        self::assertSame([], Escuela::searchByNombre('P4T Escuela Turno'), 'Sin escuela insertada.');
        self::assertSame([], Turno::searchByNombre('Temprano'), 'Sin turno creado.');
    }

    public function testNombreDuplicadoMuestraErrorAmigableSinInsertar(): void
    {
        $this->usarComo(self::$adminId);

        $respuesta = $this->despachar('POST', '/escuelas', $this->postValida(['nombre' => 'P4T Escuela']));
        self::assertSame(200, $respuesta->statusCode());
        $html = self::cuerpo($respuesta);
        self::assertStringContainsString('ya existe una escuela con ese nombre', $html, '23505 amigable (REQ-16).');
        self::assertStringContainsString('value="P4T Escuela"', $html, 'Re-render con el valor enviado.');
    }

    /**
     * POST /escuelas válido (base); $cambios pisa campos a medida. Los chips
     * duplicados ejercitan la deduplicación server-side.
     *
     * @param array<string, mixed> $cambios
     *
     * @return array<string, mixed>
     */
    private function postValida(array $cambios = []): array
    {
        $base = [
            'nombre' => 'P4T Escuela Base',
            'numero' => '1',
            'region' => '1',
            'sigla' => '',
            'anexo' => '',
            'sector' => '',
            'distrito' => 'P4T Distrito',
            'localidad' => 'La Plata',
            'direccion' => 'P4T Direccion 123',
            'codigo_postal' => '1900',
            'telefono' => '',
            'email' => '',
            'turno_nombres' => ['Mañana', 'Mañana'],
            'orientacion_nombres' => ['Artes', 'Artes'],
        ];

        return array_merge($base, $cambios);
    }

    /**
     * Despacha como index.php: cadena de middlewares, rutas de alta de escuela
     * y manejo de PermissionDeniedException del front controller.
     *
     * @param array<string, mixed> $post
     */
    private function despachar(string $method, string $path, array $post = []): Response
    {
        $router = new Router([
            new AuthMiddleware(),
            new SchoolContextMiddleware('id'),
        ]);
        $escuelas = new EscuelaController();

        $router->get('/escuelas/nueva', function (Request $r) use ($escuelas): Response {
            PermissionMiddleware::assertAuthenticated('escuela.nueva', $r);
            return $escuelas->nueva($r);
        });
        $router->post('/escuelas', function (Request $r) use ($escuelas): Response {
            PermissionMiddleware::assertAuthenticated('escuela.create', $r);
            return $escuelas->crear($r);
        });
        $router->get('/escuelas/{id}', fn (Request $r): Response => $escuelas->show($r));

        try {
            return $router->dispatch(new Request($method, $path, [], $post));
        } catch (PermissionDeniedException $exception) {
            if ($exception->shouldRedirectToLogin()) {
                return Response::redirect(Http::url('login'));
            }

            return (new Response())->status(403)->body('Access denied.');
        }
    }

    private static function conteoPivotes(string $escuelaId, string $tabla): int
    {
        $sql = $tabla === 'escuela_turno'
            ? 'SELECT count(*) FROM escuela_turno WHERE id_escuela = ?'
            : 'SELECT count(*) FROM escuela_orientacion WHERE id_escuela = ?';
        $statement = Database::getConnection()->prepare($sql);
        $statement->execute([$escuelaId]);

        return (int) $statement->fetchColumn();
    }

    private static function cuerpo(Response $response): string
    {
        ob_start();
        $response->send();

        return (string) ob_get_clean();
    }
}