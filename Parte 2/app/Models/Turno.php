<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

/**
 * Turno (tabla turno): catálogo de turnos escolares. El schema restringe los
 * nombres a {'Mañana','Tarde','Noche'} (chk_nombre_turno).
 */
final class Turno
{
    public const NOMBRES_VALIDOS = ['Mañana', 'Tarde', 'Noche'];

    private const TABLE = 'turno';

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
     * Crea un turno y devuelve la fila insertada.
     *
     * @throws \RuntimeException si el nombre no pertenece al set CHECK
     *                           (message 'turno_invalido')
     * @throws \RuntimeException si el nombre ya existe (message 'turno_duplicado')
     *
     * @return array<string, mixed>
     */
    public static function create(string $nombre): array
    {
        if (!in_array($nombre, self::NOMBRES_VALIDOS, true)) {
            throw new \RuntimeException('turno_invalido');
        }

        try {
            $statement = Database::getConnection()->prepare(
                'INSERT INTO ' . self::TABLE . ' (nombre) VALUES (?)'
                . ' RETURNING id, nombre'
            );
            $statement->execute([$nombre]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') { // unique_violation (uq_nombre_turno)
                throw new \RuntimeException('turno_duplicado');
            }
            if ($exception->getCode() === '23514') { // check_violation (chk_nombre_turno)
                throw new \RuntimeException('turno_invalido');
            }
            throw $exception;
        }

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar el turno creado.');
        }

        return $row;
    }

    /**
     * Find-or-create (D2): devuelve el turno existente o crea uno nuevo.
     * Si otro proceso lo crea entre el SELECT y el INSERT, el 23505 se
     * absorbe y se relee la fila existente. Los turnos se comparten entre
     * escuelas, por eso el duplicado NO es un error.
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
            if ($exception->getMessage() !== 'turno_duplicado') {
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
     * Autocompletar: turnos cuyo nombre contiene $q (ILIKE), at most 10.
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