<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Escuela (tabla escuela): catálogo público de escuelas y paginación.
 * Lectura pública vía RLS (pc_escuela_select usando true); la home (PR3)
 * pagina favoritas del usuario o el catálogo para visitantes.
 */
final class Escuela
{
    private const TABLE = 'escuela';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(string $id): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre, numero, sigla, anexo, sector, region, distrito, localidad, direccion, codigo_postal, telefono, email'
            . ' FROM ' . self::TABLE . ' WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Página del catálogo de escuelas, ordenada por número y nombre.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function paginar(int $offset, int $limit): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre, numero, sigla, anexo, sector, region, distrito, localidad, direccion, codigo_postal, telefono, email'
            . ' FROM ' . self::TABLE
            . ' ORDER BY numero, nombre'
            . ' LIMIT ? OFFSET ?'
        );
        $statement->execute([$limit, $offset]);

        return $statement->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::getConnection()
            ->query('SELECT count(*) FROM ' . self::TABLE)
            ->fetchColumn();
    }
}