<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\CursoController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Rutas de alta de curso dictado (formularios-autocomplete, WU2b): la ruta
 * literal /escuelas/{id}/cursos/nuevo nunca colisiona con la plantilla
 * /escuelas/{id}/cursos/{courseId} (que exige UUID v4), POST sobre
 * /escuelas/{id}/cursos es el único método de alta, y el guard de rol del
 * controlador (REQ-21) rechaza a jefe y anónimo.
 */
final class CursoNuevoRouterTest extends TestCase
{
    public function testNuevoLiteralNuncaCaeEnLaPlantillaCourseId(): void
    {
        $router = new Router();
        $router->get('/escuelas/{id}/cursos/{courseId}', fn (Request $r): Response => (new Response())->body('DETALLE:' . (string) $r->param('courseId', '')));
        $router->get('/escuelas/{id}/cursos/nuevo', fn (Request $r): Response => (new Response())->body('NUEVO'));

        $escuela = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

        $nuevo = $router->dispatch(new Request('GET', '/escuelas/' . $escuela . '/cursos/nuevo'));
        self::assertSame(200, $nuevo->statusCode());
        $cuerpo = self::cuerpo($nuevo);
        self::assertStringContainsString('NUEVO', $cuerpo, '"nuevo" no es UUID: matchea la ruta literal, no {courseId}.');
        self::assertStringNotContainsString('DETALLE', $cuerpo);

        $detalle = $router->dispatch(new Request('GET', '/escuelas/' . $escuela . '/cursos/a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
        self::assertStringContainsString('DETALLE:', self::cuerpo($detalle), 'Un UUID v4 de verdad sí entra en {courseId}.');
    }

    public function testPostEscuelasIdCursosEsElUnicoMetodoDeAlta(): void
    {
        $router = new Router();
        $router->post('/escuelas/{id}/cursos', fn (Request $r): Response => (new Response())->body('CREAR'));

        $escuela = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

        $crear = $router->dispatch(new Request('POST', '/escuelas/' . $escuela . '/cursos'));
        self::assertSame(200, $crear->statusCode());
        self::assertStringContainsString('CREAR', self::cuerpo($crear));

        $get = $router->dispatch(new Request('GET', '/escuelas/' . $escuela . '/cursos'));
        self::assertSame(405, $get->statusCode(), 'GET sobre un path solo-POST → 405.');

        $put = $router->dispatch(new Request('PUT', '/escuelas/' . $escuela . '/cursos'));
        self::assertSame(405, $put->statusCode());
    }

    public function testNuevoYCrearRechazanAJefeYAnonimoSinTocarBase(): void
    {
        $cursos = new CursoController();

        $jefe = new Request('GET', '/escuelas/a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11/cursos/nuevo');
        $jefe->setAttribute('school_role', 'jefe');
        self::assertSame(403, $cursos->nuevo($jefe)->statusCode(), 'Jefe GET nuevo → 403 (REQ-21).');

        $jefePost = new Request('POST', '/escuelas/a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11/cursos');
        $jefePost->setAttribute('school_role', 'jefe');
        self::assertSame(403, $cursos->crear($jefePost)->statusCode(), 'Jefe POST → 403.');

        $anonimo = new Request('GET', '/escuelas/a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11/cursos/nuevo');
        self::assertSame(403, $cursos->nuevo($anonimo)->statusCode(), 'Sin school_role → 403.');
    }

    private static function cuerpo(Response $response): string
    {
        ob_start();
        $response->send();

        return (string) ob_get_clean();
    }
}