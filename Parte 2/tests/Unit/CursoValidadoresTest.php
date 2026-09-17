<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\CursoController;
use PHPUnit\Framework\TestCase;

/**
 * Validadores públicos puros del alta de curso dictado (WU2b): horas HH:MM de
 * 24 h (D6), carga horaria nullable 0,01..99,99 (numeric(4,2) del schema),
 * días duplicados (REQ-24) y alcance materia↔área (D7). No requieren base.
 */
final class CursoValidadoresTest extends TestCase
{
    public function testEsHoraValidaAceptaSoloHHMMDe24Horas(): void
    {
        foreach (['00:00', '08:00', '12:30', '18:00', '23:59'] as $valida) {
            self::assertTrue(CursoController::esHoraValida($valida), "'{$valida}' es HH:MM válido.");
        }

        foreach (['24:00', '18:60', '8:00', '18:0', '18:00:00', '', 'a:b', '18-00'] as $invalida) {
            self::assertFalse(CursoController::esHoraValida($invalida), "'{$invalida}' NO es HH:MM (REQ-23).");
        }
    }

    public function testEsCargaHorariaValidaAceptaVacioYUnoODosDecimalesEnRango(): void
    {
        self::assertTrue(CursoController::esCargaHorariaValida(''), 'Vacío = carga nullable (REQ-23).');
        foreach (['0.01', '4', '4.5', '99.99'] as $valida) {
            self::assertTrue(CursoController::esCargaHorariaValida($valida), "'{$valida}' cae en numeric(4,2).");
        }

        foreach (['0', '0.00', '100', '4.555', 'abc', '4,5', '-1'] as $invalida) {
            self::assertFalse(CursoController::esCargaHorariaValida($invalida), "'{$invalida}' NO cabe en 0,01..99,99.");
        }
    }

    public function testDiaDuplicadoDetectaRepeticiones(): void
    {
        $unDia = [['dia' => 'a', 'entrada' => '08:00', 'salida' => '12:00']];
        self::assertFalse(CursoController::diaDuplicado($unDia), 'Un solo día no repite (REQ-24).');

        $repetido = [
            ['dia' => 'a', 'entrada' => '08:00', 'salida' => '12:00'],
            ['dia' => 'a', 'entrada' => '14:00', 'salida' => '18:00'],
        ];
        self::assertTrue(CursoController::diaDuplicado($repetido), 'El mismo día dos veces se rechaza.');

        $conVacia = [
            ['dia' => '', 'entrada' => '', 'salida' => ''],
            ['dia' => 'b', 'entrada' => '08:00', 'salida' => '12:00'],
        ];
        self::assertFalse(CursoController::diaDuplicado($conVacia), 'Las filas vacías se ignoran (leerFilas las descarta igual).');
    }

    public function testMateriaPerteneceArea(): void
    {
        $materia = ['id' => 'm1', 'id_area' => 'area-1'];

        self::assertTrue(
            CursoController::materiaPerteneceArea($materia, ''),
            'Sin área elegida no hay restricción de alcance (D7).'
        );
        self::assertTrue(
            CursoController::materiaPerteneceArea($materia, 'area-1'),
            'Materia de la misma área pasa.'
        );
        self::assertFalse(
            CursoController::materiaPerteneceArea($materia, 'area-2'),
            'Materia de otra área se rechaza (REQ-26/S7).'
        );
        self::assertFalse(
            CursoController::materiaPerteneceArea(null, 'area-1'),
            'Materia inexistente con área elegida no pasa.'
        );
    }
}