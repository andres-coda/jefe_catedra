<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Tarea (tabla tarea): tareas de un curso dictado, con flag "realizado" y
 * observaciones opcionales. RLS: solo admin/directivo/jefe del curso pueden
 * leer y escribir (pc_tarea_*).
 */
final class Tarea
{
    private const TABLE = 'tarea';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(string $id): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, id_curso_escuela, descripcion, observaciones, realizado'
            . ' FROM ' . self::TABLE . ' WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Tareas de un curso: pendientes primero, luego realizadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listByCurso(string $cursoEscuelaId): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, descripcion, observaciones, realizado'
            . ' FROM ' . self::TABLE . ' WHERE id_curso_escuela = ?'
            . ' ORDER BY realizado, descripcion'
        );
        $statement->execute([$cursoEscuelaId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>
     */
    public static function create(string $cursoEscuelaId, string $descripcion, ?string $observaciones = null): array
    {
        $statement = Database::getConnection()->prepare(
            'INSERT INTO ' . self::TABLE . ' (id_curso_escuela, descripcion, observaciones)'
            . ' VALUES (?, ?, ?)'
            . ' RETURNING id, id_curso_escuela, descripcion, observaciones, realizado'
        );
        $statement->execute([$cursoEscuelaId, $descripcion, $observaciones]);

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar la tarea creada.');
        }

        return $row;
    }

    public static function actualizar(string $id, string $descripcion, ?string $observaciones, bool $realizado): void
    {
        Database::getConnection()->prepare(
            'UPDATE ' . self::TABLE . ' SET descripcion = ?, observaciones = ?, realizado = ? WHERE id = ?'
        )->execute([$descripcion, $observaciones, $realizado ? 'true' : 'false', $id]);
    }

    public static function actualizarRealizado(string $id, bool $realizado): void
    {
        Database::getConnection()->prepare(
            'UPDATE ' . self::TABLE . ' SET realizado = ? WHERE id = ?'
        )->execute([$realizado ? 'true' : 'false', $id]);
    }
}