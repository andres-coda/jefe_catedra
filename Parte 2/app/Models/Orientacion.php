<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

/**
 * Orientacion (tabla orientacion): catálogo de orientaciones de una escuela.
 * Nombres libres (sin CHECK de valores, a diferencia de turno).
 */
final class Orientacion
{
    private const TABLE = 'orientacion';

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
     * Crea una orientación y devuelve la fila insertada.
     *
     * @throws \RuntimeException si el nombre ya existe (message 'orientacion_duplicada')
     *
     * @return array<string, mixed>
     */
    public static function create(string $nombre): array
    {
        try {
            $statement = Database::getConnection()->prepare(
                'INSERT INTO ' . self::TABLE . ' (nombre) VALUES (?)'
                . ' RETURNING id, nombre'
            );
            $statement->execute([$nombre]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') { // unique_violation (uq_nombre_orientacion)
                throw new \RuntimeException('orientacion_duplicada');
            }
            throw $exception;
        }

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar la orientación creada.');
        }

        return $row;
    }

    /**
     * Find-or-create (D2): devuelve la orientación existente o crea una nueva.
     * Las orientaciones se comparten entre escuelas; el duplicado se absorbe
     * y se relee la fila existente.
     *
     * @return array<string, mixed>
     */
    public static function obtenerOCrear(string $nombre): array
    {
        $existente = self::buscarPorNombreExacto($nombre);
        if ($existente !== null) {
            return $existente;
        }

        try {
            return self::create($nombre);
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() !== 'orientacion_duplicada') {
                throw $exception;
            }
            $releido = self::buscarPorNombreExacto($nombre);
            if ($releido === null) {
                throw $exception;
            }
            return $releido;
        }
    }

    /**
     * Autocompletar: orientaciones cuyo nombre contiene $q (ILIKE), at most 10.
     *
     * @return array<int, array{id: string, nombre: string}>
     */
    public static function searchByNombre(string $q): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre FROM ' . self::TABLE
            . " WHERE nombre ILIKE ? ESCAPE '\\'"
            . ' ORDER BY nombre LIMIT 10'
        );
        $statement->execute(['%' . self::escaparLike($q) . '%']);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buscarPorNombreExacto(string $nombre): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre FROM ' . self::TABLE . ' WHERE nombre = ?'
        );
        $statement->execute([$nombre]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    private static function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }
}