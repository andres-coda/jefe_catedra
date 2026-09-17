<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Models\CursoEscuela;
use App\Models\CursoEscuelaDia;
use PDOException;
use RuntimeException;
use Tests\Support\DbCase;

/**
 * Garantías a nivel DB del alta de curso dictado (formularios-autocomplete,
 * WU2b): la RLS real bloquea el insert del jefe (REQ-21/S1), la transacción
 * cooperativa (D1) revierte por completo ante un fallo tardío (REQ-25/S3), y
 * el directivo sí pasa la policy (sin backstop de controlador de por medio).
 */
final class CursoIntegracionTest extends DbCase
{
    public function testJefeNoPuedeInsertarCursoPorRls(): void
    {
        $this->usarComo(self::$jefeId);

        try {
            // Combo 2025 (el fixture solo tiene 2026): el único motivo de
            // rechazo posible es la policy, no la unicidad.
            CursoEscuela::create(self::$escuelaId, self::$cursoId, self::$materiaId, 4.0, 2025);
            self::fail('El insert del jefe debería ser rechazado por RLS.');
        } catch (PDOException $exception) {
            self::assertSame('42501', (string) $exception->getCode(), 'SQLSTATE 42501: insufficient_privilege (REQ-21/S1).');
        }
    }

    public function testTransaccionRevertePorCompletoCuandoFallaUnHorario(): void
    {
        $this->usarComo(self::$directivoId);

        try {
            Database::transaction(function (): void {
                $cursoEscuela = CursoEscuela::create(self::$escuelaId, self::$cursoId, self::$materiaId, 4.0, 2025);
                // UUID bien formado pero inexistente → FK 23503: falla DESPUÉS
                // del insert del curso dictado (REQ-25/S3).
                CursoEscuelaDia::create((string) $cursoEscuela['id'], '00000000-0000-4000-8000-000000000000', '08:00', '12:00');
            });
            self::fail('Un id de día inexistente debería violar la FK.');
        } catch (PDOException $exception) {
            self::assertSame('23503', (string) $exception->getCode(), 'FK violation al insertar el horario.');
        }

        // Conexión nueva (la transacción con el fallo queda descartada): nada
        // de lo insertado dentro de la transacción debe haber persistido.
        Database::reset();

        self::assertSame([], self::cursoEscuela2025(), 'Sin curso_escuela huérfano (REQ-25/S3).');
        self::assertSame([], self::horarios2025(), 'Sin horarios huérfanos.');
    }

    public function testDirectivoPasaLaRlsYElDuplicadoSeReportaAmigable(): void
    {
        $this->usarComo(self::$directivoId);

        try {
            // Cuarteto 2026 ya sembrado por el fixture: si el directivo pasa la
            // RLS (insert intentado de verdad), la unicidad dispara el mensaje.
            CursoEscuela::create(self::$escuelaId, self::$cursoId, self::$materiaId, 4.5, 2026);
            self::fail('El cuarteto 2026 ya existe en el fixture.');
        } catch (RuntimeException $exception) {
            self::assertSame('curso_escuela_duplicada', $exception->getMessage(), 'uq_ce_materia → mensaje amigable (REQ-26).');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function cursoEscuela2025(): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id FROM curso_escuela WHERE id_escuela = ? AND anio = 2025'
        );
        $statement->execute([self::$escuelaId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function horarios2025(): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT ced.id_curso_escuela FROM curso_escuela_dia ced'
            . ' JOIN curso_escuela ce ON ce.id = ced.id_curso_escuela'
            . ' WHERE ce.id_escuela = ? AND ce.anio = 2025'
        );
        $statement->execute([self::$escuelaId]);

        return $statement->fetchAll();
    }
}