<?php

declare(strict_types=1);

/**
 * Bootstrap de la suite PHPUnit (Fase 4).
 *
 * Reproduce el contrato de arranque de index.php: autoloader PSR-4 (primario
 * vendor/autoload.php; fallback mínimo si composer no está disponible) y la
 * configuración global de la base (config/claves.php) que Database lee.
 *
 * Los tests que tocan PostgreSQL corren contra la base LOCAL jefe_catedra con
 * RLS (ver tests/Support/DbCase.php). Ningún registro sobrevive: los writes de
 * la conexión de la app viven en transacciones que se revierten, y el fixture
 * elevado lleva el tag "P4T" y se borra al final de cada clase.
 */

const P4T_REPO = __DIR__ . '/..';

$autoload = P4T_REPO . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $file = P4T_REPO . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

global $configuracion;
require P4T_REPO . '/config/claves.php';

// Guard: la suite escribe y revierte contra PostgreSQL local; nunca apunta a
// otra base por accidente.
if (($configuracion['basenombre'] ?? '') !== 'jefe_catedra') {
    throw new RuntimeException(
        'La suite PHPUnit solo se ejecuta contra la base local "jefe_catedra" '
        . '(config/claves.php).'
    );
}

require __DIR__ . '/Support/DbCase.php';