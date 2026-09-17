<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Conexión única a PostgreSQL leyendo config/claves.php (claves: host, usuario,
 * password, basenombre, puerto). Reemplaza el config/database.php legacy.
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::connect();
        }
        return self::$connection;
    }

    /** Rompe el singleton (útil para tests con transacciones). */
    public static function reset(): void
    {
        self::$connection = null;
    }

    private static function connect(): PDO
    {
        $config = self::config();

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'],
            $config['puerto'],
            $config['basenombre']
        );

        $pdo = new PDO($dsn, $config['usuario'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::setSessionUser($pdo);

        return $pdo;
    }

    /**
     * Inyecta el usuario de la sesión PHP en el contexto RLS de PostgreSQL
     * (GUC app.user_id). Las policies usan fc_usuario_actual() para autorizar;
     * sin esta llamada, una conexión recién abierta queda anónima.
     */
    private static function setSessionUser(PDO $pdo): void
    {
        $userId = Session::get('user_id');
        if ($userId === null) {
            return; // visitante: la conexión arranca sin contexto RLS
        }

        $stmt = $pdo->prepare("SELECT set_config('app.user_id', ?, false)");
        $stmt->execute([(string) $userId]);
    }

    private static function config(): array
    {
        global $configuracion;

        if (empty($configuracion)) {
            require_once __DIR__ . '/../../config/claves.php';
        }

        return is_array($configuracion) ? $configuracion : [];
    }
}