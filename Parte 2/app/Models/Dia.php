<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Dia (tabla dia): catálogo de días de la semana (lunes..domingo).
 */
final class Dia
{
    private const TABLE = 'dia';

    /**
     * Todos los días en orden de semana (lunes primero).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function list(): array
    {
        return Database::getConnection()
            ->query(
                'SELECT id, nombre FROM ' . self::TABLE
                . " ORDER BY CASE nombre"
                . " WHEN 'lunes' THEN 1 WHEN 'martes' THEN 2 WHEN 'miercoles' THEN 3"
                . " WHEN 'jueves' THEN 4 WHEN 'viernes' THEN 5 WHEN 'sabado' THEN 6 ELSE 7 END"
            )
            ->fetchAll();
    }
}