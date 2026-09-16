<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * CursoProfesor (tabla curso_profesor): históricos del docente en un curso
 * dictado (fecha_ingreso, fecha_cese, detalles). PK compuesta
 * (id_curso_escuela, id_profesor). RLS: admin/directivo/jefe del curso.
 */
final class CursoProfesor
{
    private const TABLE = 'curso_profesor';

    /**
     * Asignaciones de un curso (activas primero) con datos del profesor.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listByCurso(string $cursoEscuelaId): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT cp.id_profesor, cp.fecha_ingreso, cp.fecha_cese, cp.detalles,'
            . ' p.nombre, p.email, p.telefono, p.id_escuela'
            . ' FROM ' . self::TABLE . ' cp'
            . ' JOIN profesor p ON p.id = cp.id_profesor'
            . ' WHERE cp.id_curso_escuela = ?'
            . ' ORDER BY (cp.fecha_cese IS NOT NULL), p.nombre'
        );
        $statement->execute([$cursoEscuelaId]);

        return $statement->fetchAll();
    }

    public static function existe(string $cursoEscuelaId, string $profesorId): bool
    {
        $statement = Database::getConnection()->prepare(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE id_curso_escuela = ? AND id_profesor = ?'
        );
        $statement->execute([$cursoEscuelaId, $profesorId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Asigna un profesor al curso. Si ya tenía una asignación cesada, la
     * reactiva (fecha_cese = NULL, fecha_ingreso = hoy).
     */
    public static function asignar(string $cursoEscuelaId, string $profesorId): void
    {
        Database::getConnection()->prepare(
            'INSERT INTO ' . self::TABLE . ' (id_curso_escuela, id_profesor, fecha_ingreso)'
            . ' VALUES (?, ?, CURRENT_DATE)'
            . ' ON CONFLICT (id_curso_escuela, id_profesor)'
            . ' DO UPDATE SET fecha_cese = NULL, fecha_ingreso = CURRENT_DATE'
        )->execute([$cursoEscuelaId, $profesorId]);
    }

    public static function actualizarAsignacion(
        string $cursoEscuelaId,
        string $profesorId,
        ?string $fechaCese,
        ?string $detalles
    ): void {
        Database::getConnection()->prepare(
            'UPDATE ' . self::TABLE . ' SET fecha_cese = ?, detalles = ?'
            . ' WHERE id_curso_escuela = ? AND id_profesor = ?'
        )->execute([$fechaCese, $detalles, $cursoEscuelaId, $profesorId]);
    }

    public static function quitarAsignacion(string $cursoEscuelaId, string $profesorId): void
    {
        Database::getConnection()->prepare(
            'DELETE FROM ' . self::TABLE . ' WHERE id_curso_escuela = ? AND id_profesor = ?'
        )->execute([$cursoEscuelaId, $profesorId]);
    }
}