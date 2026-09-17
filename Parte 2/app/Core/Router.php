<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router de recursos: registra rutas "METHOD /recurso/{id}/..." y las despacha.
 * - Parámetros cuyo nombre termina en "id" (id, tareaId, ...) se validan como UUID v4.
 * - Sin ruta que matchee el path → 404.
 * - Path conocido pero método no permitido → 405 con cabecera Allow.
 */
final class Router
{
    private const UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    /** @var array<int, array{method: string, regex: string, names: array<int, string>, handler: callable}> */
    private array $routes = [];

    /** @var array<int, Middleware> Cadena global ejecutada antes del handler. */
    private array $middlewares = [];

    /** @param array<int, Middleware> $middlewares */
    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    public function register(string $method, string $pattern, callable $handler): self
    {
        [$regex, $names] = $this->compile($pattern);

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $regex,
            'names' => $names,
            'handler' => $handler,
        ];

        return $this;
    }

    public function get(string $pattern, callable $handler): self
    {
        return $this->register('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): self
    {
        return $this->register('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): self
    {
        return $this->register('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): self
    {
        return $this->register('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): self
    {
        return $this->register('DELETE', $pattern, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();

        // Primera pasada: todas las rutas cuyo path matchea, agrupadas por método.
        // Las capturas se guardan con su ruta: un preg_match posterior que no
        // matchee reinicia $matches a [], por eso no se puede reutilizar fuera.
        $matched = [];
        foreach ($this->routes as $route) {
            if (isset($matched[$route['method']])) {
                continue; // gana la primera ruta registrada por método
            }
            if (preg_match($route['regex'], $path, $matches) === 1) {
                $matched[$route['method']] = [$route, $matches];
            }
        }

        $method = $request->method();

        if (isset($matched[$method])) {
            [$route, $matches] = $matched[$method];
            foreach ($route['names'] as $name) {
                $request->setRouteParam($name, $matches[$name]);
            }

            $handler = $route['handler'];
            $core = static function (Request $request) use ($handler): Response {
                return $handler($request);
            };

            return Middleware::run($this->middlewares, $request, $core);
        }

        if ($matched !== []) {
            $allow = implode(', ', array_keys($matched));
            return (new Response())
                ->status(405)
                ->withHeader('Allow', $allow)
                ->body('Método no permitido.');
        }

        return (new Response())
            ->status(404)
            ->body('No se encontró la página solicitada.');
    }

    public static function isUuid(string $value): bool
    {
        return preg_match('/^' . self::UUID_PATTERN . '$/', $value) === 1;
    }

    /**
     * Compila un patrón "METHOD /a/{id}/b" a regex + lista de nombres de params.
     * Los placeholders que terminan en "id" exigen UUID v4 (contrato de diseño).
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function compile(string $pattern): array
    {
        $pattern = trim($pattern, '/');

        if ($pattern === '') {
            return ['#^/$#', []];
        }

        $names = [];
        preg_match_all('/\{(\w+)\}/', $pattern, $names);
        $names = $names[1];

        $segments = [];
        foreach (explode('/', $pattern) as $segment) {
            if (preg_match('/^\{(\w+)\}$/', $segment, $m) === 1) {
                $name = $m[1];
                if (preg_match('/id$/i', $name) === 1) {
                    $segments[] = '(?P<' . $name . '>' . self::UUID_PATTERN . ')';
                } else {
                    $segments[] = '(?P<' . $name . '>[^/]+)';
                }
            } else {
                $segments[] = preg_quote($segment, '#');
            }
        }

        return ['#^/' . implode('/', $segments) . '$#', $names];
    }
}