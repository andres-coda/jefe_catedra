<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Usuario;

/**
 * Resuelve el usuario autenticado desde la sesión y lo expone como atributo
 * "user" de la Request ([id, nombre, email, rol] o null → Visitante/anónimo).
 * "rol" es el rol GLOBAL del usuario: 'admin' | 'user' (el rol por escuela
 * vive en escuela_usuario y lo resuelve SchoolContextMiddleware).
 */
final class AuthMiddleware extends Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        Session::start();

        $user = null;
        $userId = Session::get('user_id');

        if ($userId !== null) {
            $row = Usuario::findById((string) $userId);

            if ($row !== null) {
                $user = [
                    'id' => $row['id'],
                    'nombre' => $row['nombre'],
                    'email' => $row['email'],
                    'rol' => $row['rol'],
                ];
            } else {
                // Usuario eliminado mientras su sesión seguía activa.
                Session::remove('user_id');
            }
        }

        $request->setAttribute('user', $user);

        return $next($request);
    }
}