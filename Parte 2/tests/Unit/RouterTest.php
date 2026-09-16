<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Router: coincidencia de rutas, extracción de parámetros, 404/405 y
 * validación de UUID para parámetros que terminan en "id" (contrato D3).
 */
final class RouterTest extends TestCase
{
    private const UUID_V4 = 'd7a4f8c2-3b6e-4a5f-9c8d-1e2f3a4b5c6d';

    public function testRutaRaiz(): void
    {
        $router = (new Router())->get('/', fn (Request $request): Response => (new Response())->body('raiz'));

        $response = $router->dispatch(new Request('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('raiz', self::body($response));
    }

    public function testParametroUuidSeCaptura(): void
    {
        $router = (new Router())
            ->get('/escuelas/{id}', fn (Request $request): Response => (new Response())->body($request->param('id')));

        $response = $router->dispatch(new Request('GET', '/escuelas/' . self::UUID_V4));

        self::assertSame(200, $response->statusCode());
        self::assertSame(self::UUID_V4, self::body($response));
    }

    public function testParametroNoIdAceptaCualquierSegmento(): void
    {
        $router = (new Router())
            ->get('/buscar/{term}', fn (Request $request): Response => (new Response())->body($request->param('term')));

        $response = $router->dispatch(new Request('GET', '/buscar/algebra-2'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('algebra-2', self::body($response));
    }

    public function testUuidInvalidoEnParametroIdEs404(): void
    {
        $router = (new Router())
            ->get('/escuelas/{id}', fn (Request $request): Response => new Response());

        $response = $router->dispatch(new Request('GET', '/escuelas/no-es-un-uuid'));

        self::assertSame(404, $response->statusCode());
    }

    public function testUuidDeVersionInvalidaEs404(): void
    {
        // El patrón exige versiones 1-5 y variantes [89abAB].
        $router = (new Router())
            ->get('/escuelas/{id}', fn (Request $request): Response => new Response());

        $version6 = 'd7a4f8c2-3b6e-6a5f-9c8d-1e2f3a4b5c6d';
        $variant7 = 'd7a4f8c2-3b6e-4a5f-7c8d-1e2f3a4b5c6d';

        self::assertSame(404, $router->dispatch(new Request('GET', '/escuelas/' . $version6))->statusCode());
        self::assertSame(404, $router->dispatch(new Request('GET', '/escuelas/' . $variant7))->statusCode());
    }

    public function testRutaDesconocidaEs404(): void
    {
        $router = (new Router())
            ->get('/escuelas/{id}', fn (Request $request): Response => new Response());

        $response = $router->dispatch(new Request('GET', '/otra-cosa'));

        self::assertSame(404, $response->statusCode());
    }

    public function testMetodoNoPermitidoEs405(): void
    {
        $router = (new Router())
            ->get('/escuelas/{id}', fn (Request $request): Response => new Response());

        $response = $router->dispatch(new Request('POST', '/escuelas/' . self::UUID_V4));

        self::assertSame(405, $response->statusCode());
        self::assertSame('Método no permitido.', self::body($response));
    }

    public function testIsUuid(): void
    {
        self::assertTrue(Router::isUuid(self::UUID_V4));
        self::assertTrue(Router::isUuid(strtoupper(self::UUID_V4)));
        self::assertTrue(Router::isUuid('550e8400-e29b-41d4-a716-446655440000'));

        self::assertFalse(Router::isUuid(''));
        self::assertFalse(Router::isUuid('abc'));
        self::assertFalse(Router::isUuid(self::UUID_V4 . '9'));
        self::assertFalse(Router::isUuid(str_replace('-', '', self::UUID_V4)));
        self::assertFalse(Router::isUuid('d7a4f8c2-3b6e-6a5f-9c8d-1e2f3a4b5c6d')); // versión 6
        self::assertFalse(Router::isUuid('d7a4f8c2-3b6e-4a5f-7c8d-1e2f3a4b5c6d')); // variante 7
        self::assertFalse(Router::isUuid('zzzzzzzz-zzzz-4zzz-9zzz-zzzzzzzzzzzz'));
    }

    private static function body(Response $response): string
    {
        ob_start();
        $response->send();
        return (string) ob_get_clean();
    }
}