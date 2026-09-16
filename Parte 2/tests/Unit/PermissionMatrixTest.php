<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\PermissionDeniedException;
use App\Core\PermissionMiddleware;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Matriz de permisos (diseño D4): admin > directivo > jefe > user > Visitante.
 * - admin (rol GLOBAL) cubre todas las escuelas y acciones.
 * - directivo hereda jefe.
 * - Visitante (school_role null) nunca pasa → redirección a login.
 */
final class PermissionMatrixTest extends TestCase
{
    public function testVisitanteSiempreRedirigeALogin(): void
    {
        $request = $this->peticionConRol(null);

        foreach (['user', 'jefe', 'directivo', 'admin'] as $requerido) {
            try {
                PermissionMiddleware::assert($requerido, 'accion-cualquiera', $request);
                self::fail(sprintf('Rol requerido %s no debería pasar para Visitante.', $requerido));
            } catch (PermissionDeniedException $exception) {
                self::assertTrue($exception->shouldRedirectToLogin(), 'Visitante debe redirigir a login.');
            }
        }
    }

    public function testUserSoloPasaAccionesDeUser(): void
    {
        $request = $this->peticionConRol('user');

        PermissionMiddleware::assert('user', 'verDetalle', $request);

        foreach (['jefe', 'directivo', 'admin'] as $requerido) {
            $this->esperaDenegacionSinLogin($requerido, $request);
        }
    }

    public function testJefePasaUserYJefeNoDirectivoNiAdmin(): void
    {
        $request = $this->peticionConRol('jefe');

        PermissionMiddleware::assert('user', 'x', $request);
        PermissionMiddleware::assert('jefe', 'gestionarTareas', $request);

        $this->esperaDenegacionSinLogin('directivo', $request);
        $this->esperaDenegacionSinLogin('admin', $request);
    }

    public function testDirectivoHeredaJefe(): void
    {
        $request = $this->peticionConRol('directivo');

        PermissionMiddleware::assert('user', 'x', $request);
        PermissionMiddleware::assert('jefe', 'gestionarTareas', $request);
        PermissionMiddleware::assert('directivo', 'asignarRoles', $request);

        $this->esperaDenegacionSinLogin('admin', $request);
    }

    public function testAdminPasaTodo(): void
    {
        $request = $this->peticionConRol('admin');
        $roles = ['user', 'jefe', 'directivo', 'admin'];

        foreach ($roles as $requerido) {
            PermissionMiddleware::assert($requerido, 'accion-' . $requerido, $request);
        }

        self::addToAssertionCount(count($roles));
    }

    public function testSinReglaDefinidaNoBloquea(): void
    {
        $middleware = new PermissionMiddleware([]); // sin reglas: no hay guard
        $request = $this->peticionConRol(null);

        $response = $middleware->handle(
            $request,
            fn (Request $request) => (new \App\Core\Response())->body('paso')
        );

        self::assertSame(200, $response->statusCode());
    }

    public function testHandleConReglaProtegeAction(): void
    {
        $middleware = new PermissionMiddleware(['tareas' => 'jefe']);
        $request = $this->peticionConRol('user');
        $request->setAttribute('action', 'tareas');

        try {
            $middleware->handle($request, fn (Request $request) => new \App\Core\Response());
            self::fail('user no debería pasar la acción "tareas" (requiere jefe).');
        } catch (PermissionDeniedException $exception) {
            self::assertFalse($exception->shouldRedirectToLogin());
        }
    }

    private function peticionConRol(?string $schoolRole): Request
    {
        return new Request('GET', '/', [], [], [], [], ['school_role' => $schoolRole]);
    }

    private function esperaDenegacionSinLogin(string $requerido, Request $request): void
    {
        try {
            PermissionMiddleware::assert($requerido, 'accion-' . $requerido, $request);
            self::fail(sprintf('Rol requerido %s debería denegar.', $requerido));
        } catch (PermissionDeniedException $exception) {
            self::assertFalse($exception->shouldRedirectToLogin(), 'Usuario logueado insuficiente → 403, no login.');
        }
    }
}