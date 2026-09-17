<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Core\AuthMiddleware;
use App\Core\Http;
use App\Core\PermissionDeniedException;
use App\Core\PermissionMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\SchoolContextMiddleware;
use App\Controllers\PerfilController;
use Tests\Support\DbCase;

/**
 * Regression (verification CRITICAL 1): /perfil must be reachable by any
 * authenticated user — a jefe who holds a school role elsewhere AND a plain
 * registered user with no school role — while anonymous still redirects to
 * /login. The guard keys on the resolved "user" attribute (AuthMiddleware),
 * not on school_role, which is always null on /perfil (no {id} in the path).
 *
 * dispatcher() mirrors the front-controller chain of index.php (AuthMiddleware
 * + SchoolContextMiddleware) and the /perfil route closures with the exception
 * handling of the front controller, so the wiring under test matches the
 * production route table.
 */
final class PerfilAccesoE2ETest extends DbCase
{
    public function testAuthenticatedUsersReachPerfilAndAnonymousRedirectsToLogin(): void
    {
        // Jefe (school role exists on other routes, none on /perfil).
        $this->usarComo(self::$jefeId);

        $respuesta = $this->despachar('GET', '/perfil');
        self::assertSame(200, $respuesta->statusCode(), 'GET /perfil as jefe must not redirect.');
        $html = self::cuerpo($respuesta);
        self::assertStringContainsString('Mi perfil', $html);
        self::assertStringContainsString('P4T Jefe', $html, 'The jefe sees his own profile.');

        // POST update guard passes too: 302 back to /perfil, change visible.
        $editado = $this->despachar('POST', '/perfil', [
            'nombre' => 'P4T Jefe Editado',
            'email' => 'p4t.jefe@p4t.test',
            'pass_actual' => '',
            'pass' => '',
        ]);
        self::assertSame(302, $editado->statusCode(), 'POST /perfil as jefe redirects after update.');

        $verificado = $this->despachar('GET', '/perfil');
        self::assertSame(200, $verificado->statusCode());
        $html = self::cuerpo($verificado);
        self::assertStringContainsString('P4T Jefe Editado', $html, 'The updated name is persisted and shown.');
        self::assertStringContainsString('Perfil actualizado.', $html, 'The success flash is rendered.');

        // Plain registered user without any school role.
        $this->usarComo(self::$regularId);

        $respuesta = $this->despachar('GET', '/perfil');
        self::assertSame(200, $respuesta->statusCode(), 'GET /perfil as a plain user must not redirect.');
        self::assertStringContainsString('P4T Regular', self::cuerpo($respuesta));

        // Anonymous: still redirected to /login (302).
        $this->anonimo();

        $respuesta = $this->despachar('GET', '/perfil');
        self::assertSame(302, $respuesta->statusCode(), 'Anonymous GET /perfil redirects to /login.');
    }

    /**
     * Dispatches /perfil replicating index.php: middleware chain, route
     * closures and the PermissionDeniedException handling of the front
     * controller (redirect to /login vs 403).
     *
     * @param array<string, string> $post
     */
    private function despachar(string $method, string $path, array $post = []): Response
    {
        $router = new Router([
            new AuthMiddleware(),
            new SchoolContextMiddleware('id'),
        ]);
        $perfil = new PerfilController();

        $router->get('/perfil', function (Request $r) use ($perfil): Response {
            PermissionMiddleware::assertAuthenticated('perfil.view', $r);
            return $perfil->show($r);
        });
        $router->post('/perfil', function (Request $r) use ($perfil): Response {
            PermissionMiddleware::assertAuthenticated('perfil.update', $r);
            return $perfil->update($r);
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