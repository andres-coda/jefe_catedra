<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * DriveLink (tabla drive_link): enlaces de Google Drive por curso dictado.
 * La lectura es pública (pc_drive_select = true): los enlaces son visibles
 * para quienes ven el curso. La escritura la restringe RLS a
 * admin/directivo/jefe del curso; la URL además se valida en la app y en la
 * base (ch_drive_link_url: ^https?://).
 */
final class DriveLink
{
    private const TABLE = 'drive_link';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(string $id): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, id_curso_escuela, url, descripcion'
            . ' FROM ' . self::TABLE . ' WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listByCurso(string $cursoEscuelaId): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, url, descripcion'
            . ' FROM ' . self::TABLE . ' WHERE id_curso_escuela = ?'
            . ' ORDER BY descripcion, url'
        );
        $statement->execute([$cursoEscuelaId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>
     */
    public static function create(string $cursoEscuelaId, string $url, ?string $descripcion = null): array
    {
        $statement = Database::getConnection()->prepare(
            'INSERT INTO ' . self::TABLE . ' (id_curso_escuela, url, descripcion)'
            . ' VALUES (?, ?, ?)'
            . ' RETURNING id, id_curso_escuela, url, descripcion'
        );
        $statement->execute([$cursoEscuelaId, $url, $descripcion]);

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar el enlace creado.');
        }

        return $row;
    }

    public static function update(string $id, string $url, ?string $descripcion): void
    {
        Database::getConnection()->prepare(
            'UPDATE ' . self::TABLE . ' SET url = ?, descripcion = ? WHERE id = ?'
        )->execute([$url, $descripcion, $id]);
    }

    public static function delete(string $id): void
    {
        Database::getConnection()->prepare(
            'DELETE FROM ' . self::TABLE . ' WHERE id = ?'
        )->execute([$id]);
    }

    /**
     * URL de Drive válida: http(s) y formato de URL correcto.
     */
    public static function esUrlValida(string $url): bool
    {
        return strlen($url) <= 2048
            && preg_match('~^https?://~i', $url) === 1
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}