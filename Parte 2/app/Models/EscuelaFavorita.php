<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * EscuelaFavorita (tabla escuela_favorita): escuelas marcadas por un usuario.
 * El INSERT se restringe por RLS (pc_escuela_favorita_insert permite al
 * usuario crear sus propias filas); la lectura por usuario es segura vía
 * filtro id_usuario.
 */
final class EscuelaFavorita
{
    private const TABLE = 'escuela_favorita';

    public static function isFavorita(string $escuelaId, string $usuarioId): bool
    {
        $statement = Database::getConnection()->prepare(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE id_escuela = ? AND id_usuario = ?'
        );
        $statement->execute([$escuelaId, $usuarioId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Alterna el favorito de una escuela para un usuario. Devuelve true si
     * quedó marcada. Si el INSERT falla por RLS (escuela inexistente o fuera
     * de alcance), el DELETE vacío mantiene coherencia.
     */
    public static function toggle(string $escuelaId, string $usuarioId): bool
    {
        if (self::isFavorita($escuelaId, $usuarioId)) {
            self::quitar($escuelaId, $usuarioId);

            return false;
        }

        try {
            $statement = Database::getConnection()->prepare(
                'INSERT INTO ' . self::TABLE . ' (id_escuela, id_usuario) VALUES (?, ?)'
            );
            $statement->execute([$escuelaId, $usuarioId]);
        } catch (\PDOException $exception) {
            $statement = Database::getConnection()->prepare(
                'DELETE FROM ' . self::TABLE . ' WHERE id_escuela = ? AND id_usuario = ?'
            );
            $statement->execute([$escuelaId, $usuarioId]);

            return false;
        }

        return true;
    }

    /**
     * Favoritas de un usuario (paginado para la home).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listByUsuario(string $usuarioId, int $offset, int $limit): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT e.id, e.nombre, e.numero, e.sigla, e.anexo, e.sector, e.region,'
            . ' e.distrito, e.localidad, e.direccion, e.codigo_postal, e.telefono, e.email'
            . ' FROM ' . self::TABLE . ' ef'
            . ' JOIN escuela e ON e.id = ef.id_escuela'
            . ' WHERE ef.id_usuario = ?'
            . ' ORDER BY e.numero, e.nombre'
            . ' LIMIT ? OFFSET ?'
        );
        $statement->execute([$usuarioId, $limit, $offset]);

        return $statement->fetchAll();
    }

    public static function countByUsuario(string $usuarioId): int
    {
        $statement = Database::getConnection()->prepare(
            'SELECT count(*) FROM ' . self::TABLE . ' WHERE id_usuario = ?'
        );
        $statement->execute([$usuarioId]);

        return (int) $statement->fetchColumn();
    }

    private static function quitar(string $escuelaId, string $usuarioId): void
    {
        $statement = Database::getConnection()->prepare(
            'DELETE FROM ' . self::TABLE . ' WHERE id_escuela = ? AND id_usuario = ?'
        );
        $statement->execute([$escuelaId, $usuarioId]);
    }
}