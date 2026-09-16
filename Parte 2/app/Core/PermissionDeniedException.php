<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Excepción de autorización lanzada por PermissionMiddleware.
 * redirectToLogin=true → Visitante intentando acción protegida (el front
 * controller redirige a /login); false → 403.
 */
final class PermissionDeniedException extends \RuntimeException
{
    private bool $redirectToLogin;

    public function __construct(string $message = 'Permission denied.', bool $redirectToLogin = false)
    {
        parent::__construct($message);
        $this->redirectToLogin = $redirectToLogin;
    }

    public function shouldRedirectToLogin(): bool
    {
        return $this->redirectToLogin;
    }
}