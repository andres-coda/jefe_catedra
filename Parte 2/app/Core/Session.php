<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesión PHP con helpers: start idempotente, get/set/has/remove, regenerate,
 * destroy y mensajes flash de un solo uso.
 */
final class Session
{
    private const SENTINEL = "\0sdd-flash-sentinel\0";

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    /** @return mixed */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /** @param mixed $value */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function clear(): void
    {
        self::start();
        $_SESSION = [];
    }

    public static function regenerate(): bool
    {
        self::start();
        return session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::clear();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Mensaje flash de un solo uso. Con dos parámetros setea; con uno, lee y
     * borra. Devuelve null si no hay mensaje almacenado.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function flash(string $key, $value = self::SENTINEL)
    {
        self::start();
        $flashKey = '__flash_' . $key;

        if ($value === self::SENTINEL) {
            $stored = $_SESSION[$flashKey] ?? null;
            unset($_SESSION[$flashKey]);
            return $stored;
        }

        $_SESSION[$flashKey] = $value;
        return null;
    }
}