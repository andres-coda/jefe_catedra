<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Materia (tabla materia): catálogo compartido, agrupada por área.
 */
final class Materia
{
    private const TABLE = 'materia';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(string $id): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre, id_area FROM ' . self::TABLE . ' WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listByArea(string $areaId): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre, id_area FROM ' . self::TABLE . ' WHERE id_area = ? ORDER BY nombre'
        );
        $statement->execute([$areaId]);

        return $statement->fetchAll();
    }

    /**
     * Materias que se dictan en una escuela (para la vista de materias).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listByEscuela(string $escuelaId): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT DISTINCT m.id AS id, m.nombre AS nombre, a.id AS id_area, a.nombre AS area'
            . ' FROM materia m'
            . ' JOIN area a ON a.id = m.id_area'
            . ' JOIN curso_escuela ce ON ce.id_materia = m.id'
            . ' WHERE ce.id_escuela = ?'
            . ' ORDER BY a.nombre, m.nombre'
        );
        $statement->execute([$escuelaId]);

        return $statement->fetchAll();
    }
}