<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Router del autocompletar: GET /buscador/{recurso} captura cualquier
 * segmento literal (no termina en "id" → [^/]+) y no colisiona con
 * /escuelas/{id} (UUID estricto). El recurso desconocido es decisión
 * del BuscadorController (404), no del router.
 */
final class BuscadorRouterTest extends TestCase
{
    private const UUID_V4 = 'd7a4f8c2-3b6e-4a5f-9c8d-1e2f3a4b5c6d';

    public function testBuscadorCapturaRecursoLiteral(): void
    {
        $router = (new Router())
            ->get('/buscador/{recurso}', fn (Request $request): Response => (new Response())->body((string) $request->param('recurso')));

        $response = $router->dispatch(new Request('GET', '/buscador/escuela'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('escuela', self::body($response));
    }

    public function testBuscadorAceptaCualquierRecursoYQuery(): void
    {
        $router = (new Router())->get(
            '/buscador/{recurso}',
            fn (Request $request): Response => (new Response())->body(
                (string) $request->param('recurso') . ':' . (string) $request->query('q', '')
            )
        );

        $response = $router->dispatch(new Request('GET', '/buscador/materia', ['q' => 'mate']));

        self::assertSame(200, $response->statusCode());
        self::assertSame('materia:mate', self::body($response));
    }

    public function testBuscadorNoColisionaConEscuelasId(): void
    {
        $router = (new Router())
            ->get('/escuelas/{id}', fn (Request $request): Response => (new Response())->body('id:' . (string) $request->param('id')))
            ->get('/buscador/{recurso}', fn (Request $request): Response => (new Response())->body('recurso:' . (string) $request->param('recurso')));

        self::assertSame('recurso:escuela', self::body($router->dispatch(new Request('GET', '/buscador/escuela'))));
        self::assertSame('id:' . self::UUID_V4, self::body($router->dispatch(new Request('GET', '/escuelas/' . self::UUID_V4))));
    }

    public function testEscuelasNuevaNoMatcheaIdUuidNiBuscador(): void
    {
        $router = (new Router())
            ->get('/escuelas/{id}', fn (Request $request): Response => new Response())
            ->get('/buscador/{recurso}', fn (Request $request): Response => new Response());

        self::assertSame(404, $router->dispatch(new Request('GET', '/escuelas/nueva'))->statusCode());
        self::assertSame(404, $router->dispatch(new Request('GET', '/buscador/'))->statusCode());
    }

    public function testBuscadorConUuidComoRecursoSigueSiendoRecurso(): void
    {
        // Un UUID en la posición de {recurso} debe capturarse como recurso
        // (el segmento no termina en "id"), no rechazarse por validación UUID.
        $router = (new Router())
            ->get('/buscador/{recurso}', fn (Request $request): Response => (new Response())->body((string) $request->param('recurso')));

        $response = $router->dispatch(new Request('GET', '/buscador/' . self::UUID_V4));

        self::assertSame(200, $response->statusCode());
        self::assertSame(self::UUID_V4, self::body($response));
    }

    public function testSegundoSegmentoIdSigueSiendoUuidEnRutaAnidada(): void
    {
        $router = (new Router())
            ->get('/buscador/{recurso}/{id}', fn (Request $request): Response => (new Response())->body((string) $request->param('id')));

        self::assertSame(404, $router->dispatch(new Request('GET', '/buscador/escuela/no-es-uuid'))->statusCode());
        self::assertSame(
            self::UUID_V4,
            self::body($router->dispatch(new Request('GET', '/buscador/escuela/' . self::UUID_V4)))
        );
    }

    private static function body(Response $response): string
    {
        ob_start();
        $response->send();
        return (string) ob_get_clean();
    }
}