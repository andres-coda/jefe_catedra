<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

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

    /**
     * Crea una materia (requiere id_area existente) y devuelve la fila.
     *
     * @throws \RuntimeException si el nombre ya existe (message 'materia_duplicada')
     *
     * @return array<string, mixed>
     */
    public static function create(string $nombre, string $areaId): array
    {
        try {
            $statement = Database::getConnection()->prepare(
                'INSERT INTO ' . self::TABLE . ' (nombre, id_area) VALUES (?, ?)'
                . ' RETURNING id, nombre, id_area'
            );
            $statement->execute([$nombre, $areaId]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') { // unique_violation (uq_materia_nombre)
                throw new \RuntimeException('materia_duplicada');
            }
            throw $exception;
        }

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar la materia creada.');
        }

        return $row;
    }

    /**
     * Autocompletar: materias cuyo nombre contiene $q. Con $areaId no-null,
     * acota el resultado a las materias de esa área (REQ-22/S2).
     *
     * @return array<int, array{id: string, nombre: string}>
     */
    public static function searchByNombre(string $q, ?string $areaId = null): array
    {
        $sql = 'SELECT id, nombre FROM ' . self::TABLE
            . " WHERE nombre ILIKE ? ESCAPE '\\'";
        $params = ['%' . self::escaparLike($q) . '%'];

        if ($areaId !== null) {
            $sql .= ' AND id_area = ?';
            $params[] = $areaId;
        }

        $sql .= ' ORDER BY nombre LIMIT 10';

        $statement = Database::getConnection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private static function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }
}