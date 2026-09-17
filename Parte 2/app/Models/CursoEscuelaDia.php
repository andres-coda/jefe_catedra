<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * CursoEscuelaDia (tabla curso_escuela_dia): horarios (día, hora inicio/fin)
 * de un curso de escuela. Lectura restringida por RLS (id_curso_escuela y
 * la escuela via FK).
 */
final class CursoEscuelaDia
{
    private const TABLE = 'curso_escuela_dia';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listByCursoEscuela(string $idCursoEscuela): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT ced.id_curso_escuela, ced.id_dia, ced.hora_entrada, ced.hora_salida,'
            . ' d.nombre AS dia'
            . ' FROM ' . self::TABLE . ' ced'
            . ' JOIN dia d ON d.id = ced.id_dia'
            . ' WHERE ced.id_curso_escuela = ?'
            . ' ORDER BY CASE d.nombre'
            . " WHEN 'lunes' THEN 1 WHEN 'martes' THEN 2 WHEN 'miercoles' THEN 3"
            . " WHEN 'jueves' THEN 4 WHEN 'viernes' THEN 5 WHEN 'sabado' THEN 6 ELSE 7 END,"
            . ' ced.hora_entrada'
        );
        $statement->execute([$idCursoEscuela]);

        return $statement->fetchAll();
    }

    /**
     * Inserta una fila de horario (día + entrada + salida) para un curso.
     * El PK (id_curso_escuela, id_dia) limita a un horario por día;
     * el 23505 se reporta como 'dia_duplicado' para mensaje amigable.
     */
    public static function create(string $idCursoEscuela, string $idDia, string $horaEntrada, string $horaSalida): void
    {
        try {
            $statement = Database::getConnection()->prepare(
                'INSERT INTO ' . self::TABLE . ' (id_curso_escuela, id_dia, hora_entrada, hora_salida)'
                . ' VALUES (?, ?, ?, ?)'
            );
            $statement->execute([$idCursoEscuela, $idDia, $horaEntrada, $horaSalida]);
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23505') { // pk_curso_escuela_dia
                throw new \RuntimeException('dia_duplicado');
            }
            throw $exception;
        }
    }
}