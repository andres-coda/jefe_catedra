<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

/**
 * Profesor (tabla profesor): registro del docente POR ESCUELA (no tiene
 * acceso a la app). La lectura/escritura se restringe por RLS
 * (pc_profesor_* : admin/directivo/jefe de la escuela).
 *
 * Nota de schema aplicado: el docente pertenece a la escuela; su participación
 * en un curso dictado se modela con curso_profesor (ver CursoProfesor).
 */
final class Profesor
{
    private const TABLE = 'profesor';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(string $id): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre, email, telefono, detalles, id_escuela'
            . ' FROM ' . self::TABLE . ' WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    public static function perteneceAEscuela(string $id, string $escuelaId): bool
    {
        $statement = Database::getConnection()->prepare(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE id = ? AND id_escuela = ?'
        );
        $statement->execute([$id, $escuelaId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Profesores registrados en una escuela.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listByEscuela(string $escuelaId): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre, email, telefono, detalles'
            . ' FROM ' . self::TABLE . ' WHERE id_escuela = ? ORDER BY nombre'
        );
        $statement->execute([$escuelaId]);

        return $statement->fetchAll();
    }

    /**
     * Crea un profesor en la escuela y devuelve la fila insertada.
     *
     * @throws \RuntimeException si la escuela ya tiene un profesor con ese email (message 'email_existente')
     *
     * @return array<string, mixed>
     */
    public static function create(
        string $escuelaId,
        string $nombre,
        ?string $email = null,
        ?string $telefono = null,
        ?string $detalles = null
    ): array {
        try {
            $statement = Database::getConnection()->prepare(
                'INSERT INTO ' . self::TABLE . ' (id_escuela, nombre, email, telefono, detalles)'
                . ' VALUES (?, ?, ?, ?, ?)'
                . ' RETURNING id, nombre, email, telefono, detalles, id_escuela'
            );
            $statement->execute([$escuelaId, $nombre, $email, $telefono, $detalles]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') { // unique_violation (uq_profesor_escuela)
                throw new \RuntimeException('email_existente');
            }
            throw $exception;
        }

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar el profesor creado.');
        }

        return $row;
    }
}