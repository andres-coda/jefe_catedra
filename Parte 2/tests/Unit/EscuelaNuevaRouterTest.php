<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\EscuelaController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Rutas de alta de escuela (formularios-autocomplete, WU2): la ruta literal
 * /escuelas/nueva nunca colisiona con la plantilla /escuelas/{id} (que exige
 * UUID v4), POST /escuelas es el único método de alta, y el guard de rol del
 * controlador (REQ-11) rechaza a cualquier rol que no sea admin global.
 */
final class EscuelaNuevaRouterTest extends TestCase
{
    public function testNuevaLiteralNuncaCaeEnLaPlantillaId(): void
    {
        $router = new Router();
        $router->get('/escuelas/nueva', fn (Request $r): Response => (new Response())->body('NUEVA'));
        $router->get('/escuelas/{id}', fn (Request $r): Response => (new Response())->body('DETALLE:' . (string) $r->param('id', '')));

        $nueva = $router->dispatch(new Request('GET', '/escuelas/nueva'));
        self::assertSame(200, $nueva->statusCode());
        $cuerpo = self::cuerpo($nueva);
        self::assertStringContainsString('NUEVA', $cuerpo, '"nueva" no es UUID: matchea la ruta literal, no {id}.');
        self::assertStringNotContainsString('DETALLE', $cuerpo);

        $detalle = $router->dispatch(new Request('GET', '/escuelas/a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
        self::assertStringContainsString('DETALLE:', self::cuerpo($detalle), 'Un UUID v4 de verdad sí entra en {id}.');

        $invalido = $router->dispatch(new Request('GET', '/escuelas/no-es-uuid'));
        self::assertSame(404, $invalido->statusCode(), '{id} exige UUID; cualquier otra cosa → 404.');
    }

    public function testPostEscuelasEsElUnicoMetodoDeAlta(): void
    {
        $router = new Router();
        $router->post('/escuelas', fn (Request $r): Response => (new Response())->body('CREAR'));

        $crear = $router->dispatch(new Request('POST', '/escuelas'));
        self::assertSame(200, $crear->statusCode());
        self::assertStringContainsString('CREAR', self::cuerpo($crear));

        $put = $router->dispatch(new Request('PUT', '/escuelas'));
        self::assertSame(405, $put->statusCode(), 'PUT sobre un path solo-POST → 405.');

        $delete = $router->dispatch(new Request('DELETE', '/escuelas'));
        self::assertSame(405, $delete->statusCode());
    }

    public function testNuevaYCrearRechazanAQuienNoSeaAdminGlobal(): void
    {
        $escuelas = new EscuelaController();

        $jefe = new Request('GET', '/escuelas/nueva');
        $jefe->setAttribute('user', ['id' => 'x', 'nombre' => 'P4T Jefe', 'email' => 'jefe@p4t.test', 'rol' => 'user']);
        self::assertSame(403, $escuelas->nueva($jefe)->statusCode(), 'Jefe GET → 403 (REQ-11).');

        $anonimo = new Request('GET', '/escuelas/nueva');
        self::assertSame(403, $escuelas->nueva($anonimo)->statusCode(), 'Sin atributo user → 403.');

        $directivo = new Request('POST', '/escuelas');
        $directivo->setAttribute('user', ['id' => 'y', 'nombre' => 'P4T Directivo', 'email' => 'directivo@p4t.test', 'rol' => 'user']);
        self::assertSame(403, $escuelas->crear($directivo)->statusCode(), 'Directivo POST → 403.');

        $admin = new Request('POST', '/escuelas');
        $admin->setAttribute('user', ['id' => 'z', 'nombre' => 'P4T Admin', 'email' => 'admin@p4t.test', 'rol' => 'admin']);
        self::assertSame(200, $escuelas->crear($admin)->statusCode(), 'Admin pasa el guard; la validación re-renderiza (200).');
    }

    private static function cuerpo(Response $response): string
    {
        ob_start();
        $response->send();

        return (string) ob_get_clean();
    }
}