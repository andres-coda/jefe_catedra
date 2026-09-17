<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Models\DriveLink;
use App\Models\Escuela;
use App\Models\EscuelaFavorita;
use App\Models\EscuelaUsuario;
use App\Models\Tarea;
use App\Models\Usuario;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\DbCase;

/**
 * Modelos + esquema (DB local jefe_catedra con RLS):
 *  - el esquema real no contiene el legado (curso_completo / fc_login);
 *  - los modelos con métodos de escritura hacen CRUD transaccional dentro de
 *    contexto real por rol; cada test revierte su transacción.
 */
final class ModelTest extends DbCase
{
    private const TABLAS_ESPERADAS = [
        'usuario', 'curso', 'profesor', 'materia', 'area', 'escuela',
        'curso_escuela', 'dia', 'curso_escuela_dia', 'orientacion', 'turno',
        'escuela_turno', 'escuela_orientacion', 'tarea', 'escuela_usuario',
        'escuela_favorita', 'drive_link', 'curso_profesor',
    ];

    public function testEsquemaSinLegado(): void
    {
        $schema = (string) file_get_contents(P4T_REPO . '/db/schema.sql');

        self::assertStringNotContainsString('curso_completo', $schema, 'No debe existir la tabla legacy curso_completo.');
        self::assertStringNotContainsString('fc_login', $schema, 'No debe existir la función legacy fc_login.');
    }

    public function testTablasPublicasSinLegado(): void
    {
        $statement = Database::getConnection()->query(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
        );
        $tablas = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));

        self::assertGreaterThanOrEqual(count(self::TABLAS_ESPERADAS), count($tablas));
        foreach (self::TABLAS_ESPERADAS as $esperada) {
            self::assertContains($esperada, $tablas, sprintf('Falta la tabla %s del diseño.', $esperada));
        }
        self::assertNotContains('curso_completo', $tablas);
        self::assertNotContains('curso_completo_profe', $tablas);
    }

    public function testNoExisteFuncionLegacyFcLogin(): void
    {
        $count = (int) Database::getConnection()
            ->query("SELECT count(*) FROM pg_proc WHERE proname = 'fc_login'")
            ->fetchColumn();

        self::assertSame(0, $count, 'fc_login (legacy) no debe existir en la base.');
    }

    public function testEscuelasLecturaPublica(): void
    {
        $this->anonimo();

        $escuela = Escuela::findById(self::$escuelaId);
        self::assertNotNull($escuela);
        self::assertSame('P4T Escuela', $escuela['nombre']);

        self::assertGreaterThanOrEqual(13, Escuela::count(), 'El fixture siembra 13+ escuelas para paginación.');
        self::assertCount(12, Escuela::paginar(0, 12), 'Una página devuelve exactamente 12 filas.');
    }

    public function testTareaCrudComoJefe(): void
    {
        $this->usarComo(self::$jefeId);

        $creada = Tarea::create(self::$cursoEscuelaId, 'P4T tarea de prueba', 'obs');
        self::assertNotEmpty($creada['id']);

        $leida = Tarea::findById($creada['id']);
        self::assertNotNull($leida);
        self::assertSame('P4T tarea de prueba', $leida['descripcion']);

        Tarea::actualizar($creada['id'], 'P4T tarea editada', null, true);
        self::assertSame('P4T tarea editada', Tarea::findById($creada['id'])['descripcion']);
        self::assertTrue(self::esVerdadero(Tarea::findById($creada['id'])['realizado']));

        Tarea::actualizarRealizado($creada['id'], false);
        self::assertFalse(self::esVerdadero(Tarea::findById($creada['id'])['realizado']));

        $lista = Tarea::listByCurso(self::$cursoEscuelaId);
        self::assertNotEmpty($lista, 'El jefe ve las tareas de su curso.');
    }

    public function testDriveLinkCrudComoJefe(): void
    {
        $this->usarComo(self::$jefeId);

        $creado = DriveLink::create(self::$cursoEscuelaId, 'https://drive.google.com/p4t-test', 'Material P4T');
        self::assertNotEmpty($creado['id']);

        DriveLink::update($creado['id'], 'https://drive.google.com/p4t-test-2', 'Material P4T 2');
        $leido = DriveLink::findById($creado['id']);
        self::assertSame('https://drive.google.com/p4t-test-2', $leido['url']);

        DriveLink::delete($creado['id']);
        self::assertNull(DriveLink::findById($creado['id']), 'El jefe borra sus enlaces del curso.');
    }

    public function testRegistroPublicoAnonimo(): void
    {
        $this->anonimo();

        $creado = Usuario::create('P4T Nuevo', 'p4t.nuevo@p4t.test', 'p4t-clave-nueva');
        self::assertNotEmpty($creado['id']);
        self::assertSame('user', $creado['rol'], 'El registro público siempre crea rol user.');

        $auth = Usuario::findForAuth('p4t.nuevo@p4t.test');
        self::assertNotNull($auth);
        self::assertSame($creado['id'], $auth['id']);
    }

    public function testEmailDuplicadoLanzaEmailExistente(): void
    {
        $this->anonimo();

        Usuario::create('P4T Duplicado', 'p4t.dup@p4t.test', 'p4t-clave');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('email_existente');
        Usuario::create('P4T Otro', 'p4t.dup@p4t.test', 'p4t-clave');
    }

    public function testFavoritosAlternanParaElPropioUsuario(): void
    {
        $this->usarComo(self::$jefeId);

        self::assertFalse(EscuelaFavorita::isFavorita(self::$escuelaId, self::$jefeId));
        self::assertTrue(EscuelaFavorita::toggle(self::$escuelaId, self::$jefeId), 'Primer toggle marca como favorita.');
        self::assertTrue(EscuelaFavorita::isFavorita(self::$escuelaId, self::$jefeId));
        self::assertFalse(EscuelaFavorita::toggle(self::$escuelaId, self::$jefeId), 'Segundo toggle desmarca.');
        self::assertFalse(EscuelaFavorita::isFavorita(self::$escuelaId, self::$jefeId));
    }

    public function testAsignarRolPorEmailComoDirectivo(): void
    {
        $this->usarComo(self::$directivoId);

        $asignado = EscuelaUsuario::assignByEmail(self::$escuelaId, 'p4t.regular@p4t.test', 'directivo');
        self::assertNotNull($asignado);
        self::assertSame('directivo', $asignado['rol']);

        self::assertSame('directivo', EscuelaUsuario::getRol(self::$escuelaId, self::$regularId));

        self::assertNull(
            EscuelaUsuario::assignByEmail(self::$escuelaId, 'nadie@p4t.test', 'directivo'),
            'Un email no registrado no asigna rol.'
        );
    }

    /** Normaliza boolean de PostgreSQL ('t'/'f' o bool nativo) a PHP bool. */
    private static function esVerdadero($valor): bool
    {
        return in_array($valor, [true, 1, '1', 't', 'true'], true);
    }
}