<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\EscuelaUsuario;

/**
 * Resuelve el contexto de escuela para rutas con escuela en el path
 * (/escuelas/{id}/...). Expone atributos "school_id" y "school_role".
 *
 * Reglas (diseño D4 + override schema usuario.rol):
 * - Usuario con rol GLOBAL 'admin' (usuario.rol) cubre todas las escuelas → rol "admin".
 * - Usuario con pivot escuela_usuario → rol del pivot (directivo | jefe | user).
 * - Sin pivot o anónimo → school_role null (Visitante; el guard decide).
 */
final class SchoolContextMiddleware extends Middleware
{
    private string $paramName;

    public function __construct(string $paramName = 'id')
    {
        $this->paramName = $paramName;
    }

    public function handle(Request $request, callable $next): Response
    {
        $schoolId = $request->param($this->paramName);

        $request->setAttribute('school_id', null);
        $request->setAttribute('school_role', null);

        if ($schoolId !== null && Router::isUuid($schoolId)) {
            $user = $request->getAttribute('user');

            if ($user !== null && ($user['rol'] ?? '') === 'admin') {
                $request->setAttribute('school_id', $schoolId);
                $request->setAttribute('school_role', 'admin');
            } elseif ($user !== null) {
                $rol = EscuelaUsuario::getRol($schoolId, (string) $user['id']);

                if ($rol !== null) {
                    $request->setAttribute('school_id', $schoolId);
                    $request->setAttribute('school_role', $rol);
                }
            }
        }

        return $next($request);
    }
}