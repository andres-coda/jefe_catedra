<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

/**
 * Curso (tabla curso): catálogo compartido de cursos (1 ANO, 2 ANO, ...).
 */
final class Curso
{
    private const TABLE = 'curso';

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
     * Crea un curso y devuelve la fila insertada.
     *
     * @throws \RuntimeException si el nombre ya existe (message 'curso_duplicado')
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
            if ($exception->getCode() === '23505') { // unique_violation (uq_curso_nombre)
                throw new \RuntimeException('curso_duplicado');
            }
            throw $exception;
        }

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar el curso creado.');
        }

        return $row;
    }

    /**
     * Autocompletar: cursos cuyo nombre contiene $q (ILIKE), at most 10.
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

    private static function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }
}