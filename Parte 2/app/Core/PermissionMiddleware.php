<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Guard de permisos por rol y por acción.
 *
 * Contrato de diseño: PermissionGuard::assert(string $role, string $action): void
 * lee Request->schoolRole (puesto por SchoolContextMiddleware) y lanza
 * PermissionDeniedException cuando el rol no alcanza.
 *
 * Jerarquía (matriz de roles): admin > directivo > jefe > user > Visitante (null).
 * - admin (usuario con rol GLOBAL 'admin' — usuario.rol) cubre todas las escuelas.
 * - directivo hereda jefe.
 * - Visitante/anonimo (school_role null) nunca pasa un assert → redirección a login.
 */
final class PermissionMiddleware extends Middleware
{
    private const RANKS = [
        'user' => 1,
        'jefe' => 2,
        'directivo' => 3,
        'admin' => 4,
    ];

    /** @var array<string, string> Mapa acción => rol requerido. */
    private array $rules;

    /** @param array<string, string> $rules */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    public function handle(Request $request, callable $next): Response
    {
        $action = (string) $request->getAttribute('action', '');
        if (isset($this->rules[$action])) {
            self::assert($this->rules[$action], $action, $request);
        }
        return $next($request);
    }

    public static function assert(string $requiredRole, string $action, Request $request): void
    {
        $schoolRole = $request->getAttribute('school_role');

        if ($schoolRole === null) {
            // Visitante: acción protegida → login.
            throw new PermissionDeniedException(
                sprintf('Anonymous user denied for action "%s".', $action),
                true
            );
        }

        if ($schoolRole === 'admin') {
            return; // superusuario: todas las escuelas, todas las acciones
        }

        $requiredRank = self::RANKS[$requiredRole] ?? 0;
        $actualRank = self::RANKS[$schoolRole] ?? 0;

        if ($actualRank < $requiredRank) {
            throw new PermissionDeniedException(
                sprintf(
                    'Role "%s" lacks "%s" for action "%s".',
                    $schoolRole,
                    $requiredRole,
                    $action
                ),
                false
            );
        }
    }
}