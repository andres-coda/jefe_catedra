<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Escuela;
use App\Models\Turno;
use PDOException;
use Tests\Support\DbCase;

/**
 * Garantías a nivel DB del alta de escuela (formularios-autocomplete, WU2):
 * la RLS real bloquea el insert del jefe (REQ-11/S1) y la transacción
 * cooperativa (D1) revierte por completo ante un fallo tardío (REQ-14/S3).
 */
final class EscuelaIntegracionTest extends DbCase
{
    public function testJefeNoPuedeInsertarEscuelaPorRls(): void
    {
        $this->usarComo(self::$jefeId);

        try {
            Escuela::create([
                'nombre' => 'P4T Escuela Jefe',
                'numero' => 1,
                'sigla' => 'EES',
                'anexo' => null,
                'sector' => '0',
                'region' => 1,
                'distrito' => 'P4T Distrito',
                'localidad' => 'La Plata',
                'direccion' => 'P4T Direccion',
                'codigo_postal' => '1900',
                'telefono' => null,
                'email' => null,
            ]);
            self::fail('El insert del jefe debería ser rechazado por RLS.');
        } catch (PDOException $exception) {
            self::assertSame('42501', (string) $exception->getCode(), 'SQLSTATE 42501: insufficient_privilege.');
        }
    }

    public function testTransaccionRevertePorCompletoCuandoFallaLaOrientacion(): void
    {
        $this->usarComo(self::$adminId);

        try {
            Database::transaction(function (): void {
                $escuela = Escuela::create([
                    'nombre' => 'P4T Rollback',
                    'numero' => 2,
                    'sigla' => 'EES',
                    'anexo' => null,
                    'sector' => '0',
                    'region' => 1,
                    'distrito' => 'P4T Distrito',
                    'localidad' => 'La Plata',
                    'direccion' => 'P4T Direccion',
                    'codigo_postal' => '1900',
                    'telefono' => null,
                    'email' => null,
                ]);
                $turnoId = (string) Turno::obtenerOCrear('Mañana')['id'];
                Escuela::agregarTurno((string) $escuela['id'], $turnoId);

                // UUID bien formado pero inexistente → FK 23503: falla DESPUÉS
                // del insert de la escuela y del turno (REQ-14/S3).
                Escuela::agregarOrientacion((string) $escuela['id'], '00000000-0000-4000-8000-000000000000');
            });
            self::fail('Un id de orientación inexistente debería violar la FK.');
        } catch (PDOException $exception) {
            self::assertSame('23503', (string) $exception->getCode(), 'FK violation al pivotear la orientación.');
        }

        // Conexión nueva (la transacción con el fallo queda descartada): nada
        // de lo insertado dentro de la transacción debe haber persistido.
        Database::reset();

        self::assertSame([], Escuela::searchByNombre('P4T Rollback'), 'Sin escuela huérfana (REQ-14/S3).');
        self::assertSame([], Turno::searchByNombre('Mañana'), 'Sin turno huérfano creado on-submit.');
    }
}