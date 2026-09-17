<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Models\Area;
use App\Models\Curso;
use App\Models\CursoEscuela;
use App\Models\CursoEscuelaDia;
use App\Models\Dia;
use App\Models\Escuela;
use App\Models\Materia;
use App\Models\Orientacion;
use App\Models\Turno;
use Tests\Support\DbCase;

/**
 * Modelos de escritura de formularios-autocomplete (D2/D3):
 *  - create() con claves de máquina amigables en duplicados
 *    (área/materia/curso/escuela/curso_escuela, patrón Profesor::create);
 *  - find-or-create para turno/orientacion (obtenerOCrear);
 *  - Turno valida el set CHECK {'Mañana','Tarde','Noche'} en PHP
 *    (REQ-15) además del 23514 de la base;
 *  - searchByNombre() con ILIKE escape de %/_ y scope por área en Materia.
 * Todo corre como admin global dentro de una transacción revertida (DbCase).
 */
final class ModelCreateTest extends DbCase
{
    public function testAreaCreateYDuplicado(): void
    {
        $this->usarComo(self::$adminId);

        $creada = Area::create('P4T Area Nueva');
        self::assertNotEmpty($creada['id']);
        self::assertSame('P4T Area Nueva', $creada['nombre']);

        try {
            Area::create('P4T Area Nueva');
            self::fail('El área duplicada debe lanzar area_duplicada.');
        } catch (\RuntimeException $exception) {
            self::assertSame('area_duplicada', $exception->getMessage());
        }
    }

    public function testAreaSearchByNombreCaseInsensitive(): void
    {
        $this->usarComo(self::$adminId);

        Area::create('P4T Alpha');
        Area::create('P4T Beta');

        $resultados = Area::searchByNombre('p4t al');
        self::assertGreaterThanOrEqual(1, count($resultados));
        self::assertSame('P4T Alpha', $resultados[0]['nombre']);
        self::assertArrayHasKey('id', $resultados[0]);
        self::assertArrayHasKey('nombre', $resultados[0]);
    }

    public function testMateriaCreateYDuplicado(): void
    {
        $this->usarComo(self::$adminId);

        $creada = Materia::create('P4T Mat Nueva', self::$areaId);
        self::assertNotEmpty($creada['id']);
        self::assertSame(self::$areaId, $creada['id_area']);

        try {
            Materia::create('P4T Mat Nueva', self::$areaId);
            self::fail('La materia duplicada debe lanzar materia_duplicada.');
        } catch (\RuntimeException $exception) {
            self::assertSame('materia_duplicada', $exception->getMessage());
        }
    }

    public function testMateriaSearchScopedPorArea(): void
    {
        $this->usarComo(self::$adminId);

        $areaA = Area::create('P4T Area A');
        $areaB = Area::create('P4T Area B');
        Materia::create('P4T Mat A1', (string) $areaA['id']);
        Materia::create('P4T Mat B1', (string) $areaB['id']);

        $resultados = Materia::searchByNombre('P4T Mat', (string) $areaA['id']);

        self::assertCount(1, $resultados, 'El scope por área acota las materias.');
        self::assertSame('P4T Mat A1', $resultados[0]['nombre']);
    }

    public function testCursoCreateYDuplicado(): void
    {
        $this->usarComo(self::$adminId);

        $creado = Curso::create('P4T Curso Nuevo');
        self::assertNotEmpty($creado['id']);
        self::assertSame('P4T Curso Nuevo', $creado['nombre']);

        try {
            Curso::create('P4T Curso Nuevo');
            self::fail('El curso duplicado debe lanzar curso_duplicado.');
        } catch (\RuntimeException $exception) {
            self::assertSame('curso_duplicado', $exception->getMessage());
        }
    }

    public function testTurnoInvalidoRechazadoEnPhp(): void
    {
        $this->usarComo(self::$adminId);

        try {
            Turno::create('Temprano');
            self::fail('Un turno fuera del set CHECK debe lanzar turno_invalido.');
        } catch (\RuntimeException $exception) {
            self::assertSame('turno_invalido', $exception->getMessage());
        }

        try {
            Turno::create('mañana');
            self::fail('El set es case-sensitive: "mañana" no es válido.');
        } catch (\RuntimeException $exception) {
            self::assertSame('turno_invalido', $exception->getMessage());
        }
    }

    public function testTurnoObtenerOCrearEsIdempotente(): void
    {
        $this->usarComo(self::$adminId);

        $primero = Turno::obtenerOCrear('Mañana');
        $segundo = Turno::obtenerOCrear('Mañana');

        self::assertNotEmpty($primero['id']);
        self::assertSame($primero['id'], $segundo['id'], 'obtenerOCrear no duplica el turno existente.');

        $resultados = Turno::searchByNombre('Mañ');
        $ids = array_column($resultados, 'id');
        self::assertContains($primero['id'], $ids, 'El turno aparece en la búsqueda del autocompletar.');
    }

    public function testOrientacionCreateDuplicadoYFindOrCreate(): void
    {
        $this->usarComo(self::$adminId);

        $creada = Orientacion::create('P4T Orientacion Nueva');
        self::assertNotEmpty($creada['id']);

        // find-or-create ANTES del duplicado: un INSERT fallido (23505) deja la
        // transacción abortada (25P02), así que el duplicado es el último paso.
        $reusada = Orientacion::obtenerOCrear('P4T Orientacion Nueva');
        self::assertSame($creada['id'], $reusada['id'], 'obtenerOCrear reusa la existente sin lanzar.');

        try {
            Orientacion::create('P4T Orientacion Nueva');
            self::fail('La orientación duplicada debe lanzar orientacion_duplicada.');
        } catch (\RuntimeException $exception) {
            self::assertSame('orientacion_duplicada', $exception->getMessage());
        }
    }

    public function testEscuelaCreateYDuplicado(): void
    {
        $this->usarComo(self::$adminId);

        $creada = Escuela::create([
            'nombre' => 'P4T Escuela Nueva',
            'numero' => 9501,
            'sigla' => 'EES',
            'sector' => '0',
            'region' => 1,
            'distrito' => 'P4T Distrito',
            'localidad' => 'La Plata',
            'direccion' => 'P4T Direccion',
            'codigo_postal' => '1900',
            'telefono' => null,
            'email' => null,
        ]);
        self::assertNotEmpty($creada['id']);
        self::assertSame('P4T Escuela Nueva', $creada['nombre']);

        try {
            Escuela::create([
                'nombre' => 'P4T Escuela Nueva',
                'numero' => 9501,
                'region' => 1,
                'distrito' => 'P4T Distrito',
                'localidad' => 'La Plata',
                'direccion' => 'P4T Direccion',
                'codigo_postal' => '1900',
            ]);
            self::fail('La escuela duplicada debe lanzar escuela_duplicada.');
        } catch (\RuntimeException $exception) {
            self::assertSame('escuela_duplicada', $exception->getMessage());
        }
    }

    public function testEscuelaAgregarTurnoYOrientacion(): void
    {
        $this->usarComo(self::$adminId);

        $escuela = Escuela::create([
            'nombre' => 'P4T Escuela Pivots',
            'numero' => 9502,
            'region' => 1,
            'distrito' => 'P4T Distrito',
            'localidad' => 'La Plata',
            'direccion' => 'P4T Direccion',
            'codigo_postal' => '1900',
        ]);
        $turno = Turno::obtenerOCrear('Tarde');
        $orientacion = Orientacion::create('P4T Orientacion Pivot');

        Escuela::agregarTurno((string) $escuela['id'], (string) $turno['id']);
        Escuela::agregarOrientacion((string) $escuela['id'], (string) $orientacion['id']);

        $stmt = Database::getConnection()->prepare('SELECT count(*) FROM escuela_turno WHERE id_escuela = ? AND id_turno = ?');
        $stmt->execute([$escuela['id'], $turno['id']]);
        self::assertSame(1, (int) $stmt->fetchColumn(), 'El pivot turno queda vinculado.');

        $stmt = Database::getConnection()->prepare('SELECT count(*) FROM escuela_orientacion WHERE id_escuela = ? AND id_orientacion = ?');
        $stmt->execute([$escuela['id'], $orientacion['id']]);
        self::assertSame(1, (int) $stmt->fetchColumn(), 'El pivot orientación queda vinculado.');
    }

    public function testCursoEscuelaCreateYQuartetDuplicado(): void
    {
        $this->usarComo(self::$adminId);

        $creado = CursoEscuela::create(self::$escuelaId, self::$cursoId, self::$materiaId, 2.5, 2025);
        self::assertNotEmpty($creado['id']);
        self::assertSame(2025, (int) $creado['anio']);

        // Otro anio → combinación distinta → permitida (uq_ce_materia incluye
        // anio). Se prueba ANTES del duplicado: un INSERT fallido (23505) deja
        // la transacción abortada (25P02), así que el duplicado es terminal.
        $otroAnio = CursoEscuela::create(self::$escuelaId, self::$cursoId, self::$materiaId, 2.5, 2024);
        self::assertNotEmpty($otroAnio['id']);

        try {
            CursoEscuela::create(self::$escuelaId, self::$cursoId, self::$materiaId, 2.5, 2025);
            self::fail('El cuarteto duplicado debe lanzar curso_escuela_duplicada.');
        } catch (\RuntimeException $exception) {
            self::assertSame('curso_escuela_duplicada', $exception->getMessage());
        }
    }

    public function testCursoEscuelaDiaCreateYDuplicadoDia(): void
    {
        $this->usarComo(self::$adminId);

        $ce = CursoEscuela::create(self::$escuelaId, self::$cursoId, self::$materiaId, 3.0, 2023);
        $dias = Dia::list();
        self::assertGreaterThanOrEqual(7, count($dias), 'El fixture siembra los 7 días.');

        $diaId = (string) $dias[0]['id'];
        CursoEscuelaDia::create((string) $ce['id'], $diaId, '08:00', '10:00');

        try {
            CursoEscuelaDia::create((string) $ce['id'], $diaId, '10:00', '12:00');
            self::fail('El día duplicado debe lanzar dia_duplicado (PK id_curso_escuela+id_dia).');
        } catch (\RuntimeException $exception) {
            self::assertSame('dia_duplicado', $exception->getMessage());
        }
    }
}