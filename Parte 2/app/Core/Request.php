<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Representación inmutable de la petición HTTP entrante.
 */
final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $post;
    private array $headers;
    private array $routeParams;
    private array $attributes;

    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $post = [],
        array $headers = [],
        array $routeParams = [],
        array $attributes = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->post = $post;
        $this->headers = $headers;
        $this->routeParams = $routeParams;
        $this->attributes = $attributes;
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');

        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }
        } else {
            foreach ($_SERVER as $name => $value) {
                if (strpos($name, 'HTTP_') === 0) {
                    $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
                }
            }
        }

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $path,
            $_GET,
            $_POST,
            $headers
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /** @return mixed */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /** @return mixed */
    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    public function body(): string
    {
        return (string) file_get_contents('php://input');
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function ip(): string
    {
        $forwarded = $this->header('x-forwarded-for');
        if ($forwarded !== null) {
            return trim(explode(',', $forwarded)[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /** @return mixed */
    public function param(string $key, $default = null)
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setRouteParam(string $key, string $value): void
    {
        $this->routeParams[$key] = $value;
    }

    /** @return mixed */
    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }
}