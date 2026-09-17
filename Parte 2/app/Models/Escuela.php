<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

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

    /**
     * Crea una escuela y devuelve la fila insertada.
     *
     * @param array{nombre: string, numero: int, sigla?: string, anexo?: string|null,
     *             sector?: string, region: int, distrito: string, localidad: string,
     *             direccion: string, codigo_postal: string, telefono?: string|null,
     *             email?: string|null} $datos
     * @throws \RuntimeException si el nombre ya existe (message 'escuela_duplicada')
     *
     * @return array<string, mixed>
     */
    public static function create(array $datos): array
    {
        // Solo se insertan las columnas provistas (no nulas); el resto usa los
        // DEFAULT del esquema (p. ej. sigla 'EES', sector '0').
        $columnas = ['nombre', 'numero', 'sigla', 'anexo', 'sector', 'region', 'distrito', 'localidad', 'direccion', 'codigo_postal', 'telefono', 'email'];
        $insertadas = [];
        $valores = [];
        foreach ($columnas as $columna) {
            if (array_key_exists($columna, $datos) && $datos[$columna] !== null) {
                $insertadas[] = $columna;
                $valores[] = $datos[$columna];
            }
        }

        try {
            $statement = Database::getConnection()->prepare(
                'INSERT INTO ' . self::TABLE . ' ('
                . implode(', ', $insertadas)
                . ') VALUES ('
                . implode(', ', array_fill(0, count($insertadas), '?'))
                . ') RETURNING id, nombre, numero, sigla, anexo, sector, region, distrito, localidad, direccion, codigo_postal, telefono, email'
            );
            $statement->execute($valores);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') { // unique_violation (uq_escuela_nombre)
                throw new \RuntimeException('escuela_duplicada');
            }
            throw $exception;
        }

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar la escuela creada.');
        }

        return $row;
    }

    /**
     * Vincula un turno a la escuela (pivot escuela_turno).
     */
    public static function agregarTurno(string $escuelaId, string $turnoId): void
    {
        Database::getConnection()->prepare(
            'INSERT INTO escuela_turno (id_escuela, id_turno) VALUES (?, ?)'
            . ' ON CONFLICT (id_escuela, id_turno) DO NOTHING'
        )->execute([$escuelaId, $turnoId]);
    }

    /**
     * Vincula una orientación a la escuela (pivot escuela_orientacion).
     */
    public static function agregarOrientacion(string $escuelaId, string $orientacionId): void
    {
        Database::getConnection()->prepare(
            'INSERT INTO escuela_orientacion (id_escuela, id_orientacion) VALUES (?, ?)'
            . ' ON CONFLICT (id_escuela, id_orientacion) DO NOTHING'
        )->execute([$escuelaId, $orientacionId]);
    }

    /**
     * Autocompletar: escuelas cuyo nombre contiene $q (ILIKE), at most 10.
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