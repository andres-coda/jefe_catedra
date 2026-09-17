<?php

declare(strict_types=1);

namespace App\Core;

/**
 * URLs de la app para redirects y enlaces. base_url (config/claves.php) puede
 * estar vacía (URLs root-relative) o con barra final (ej. https://dominio/).
 * Http::url('login') devuelve siempre una URL que el navegador resuelve igual
 * desde cualquier página.
 */
final class Http
{
    public static function url(string $path): string
    {
        global $configuracion;

        $base = $configuracion['base_url'] ?? '';
        $path = '/' . ltrim($path, '/');

        return $base !== '' ? rtrim($base, '/') . $path : $path;
    }
}