<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base de la cadena de middlewares (diseño D3):
 * Session → Auth → SchoolContext (rol por escuela) → Permission guard.
 */
abstract class Middleware
{
    /**
     * @param callable(Request): Response $next
     */
    abstract public function handle(Request $request, callable $next): Response;

    /**
     * Ejecuta la cadena completa sobre la petición, con $core como último eslabón.
     *
     * @param array<int, Middleware> $middlewares
     * @param callable(Request): Response $core
     */
    public static function run(array $middlewares, Request $request, callable $core): Response
    {
        $pipeline = $core;
        foreach (array_reverse($middlewares) as $middleware) {
            $pipeline = static function (Request $request) use ($middleware, $pipeline): Response {
                return $middleware->handle($request, $pipeline);
            };
        }
        return $pipeline($request);
    }
}