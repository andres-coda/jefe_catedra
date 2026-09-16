<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Render de vistas .phtml. View::render() compila un template con variables
 * extraídas en un buffer y devuelve el HTML; View::e() escapa output de forma
 * consistente (htmlspecialchars ENT_QUOTES UTF-8).
 */
final class View
{
    /**
     * @param array<string, mixed> $vars
     */
    public static function render(string $template, array $vars = []): string
    {
        $file = __DIR__ . '/../template/' . $template . '.phtml';

        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('Template no encontrado: %s', $template));
        }

        extract($vars, EXTR_SKIP);

        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /**
     * @param mixed $value
     */
    public static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}