<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

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

    /**
     * Crea un área y devuelve la fila insertada.
     *
     * @throws \RuntimeException si el nombre ya existe (message 'area_duplicada')
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
            if ($exception->getCode() === '23505') { // unique_violation (uq_area_nombre)
                throw new \RuntimeException('area_duplicada');
            }
            throw $exception;
        }

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar el área creada.');
        }

        return $row;
    }

    /**
     * Autocompletar: busca áreas cuyo nombre contiene $q (ILIKE, escape de
     * % y _), at most 10, ordenadas por nombre.
     *
     * @return array<int, array{id: string, nombre: string}>
     */
    public static function searchByNombre(string $q): array
    {
        return self::buscarPorNombre($q);
    }

    /**
     * @return array<int, array{id: string, nombre: string}>
     */
    private static function buscarPorNombre(string $q): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre FROM ' . self::TABLE
            . " WHERE nombre ILIKE ? ESCAPE '\\'"
            . ' ORDER BY nombre LIMIT 10'
        );
        $statement->execute(['%' . self::escaparLike($q) . '%']);

        return $statement->fetchAll();
    }

    private static function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }
}