<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Area (tabla area): catálogo compartido de áreas (políticas públicas).
 */
final class Area
{
    private const TABLE = 'area';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(string $id): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre FROM ' . self::TABLE . ' WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list(): array
    {
        return Database::getConnection()
            ->query('SELECT id, nombre FROM ' . self::TABLE . ' ORDER BY nombre')
            ->fetchAll();
    }
}