<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Core\Session;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base para los tests que tocan PostgreSQL local (Model, Integration, E2E).
 *
 * Patrón de aislamiento (el mismo que usó el harness de PR3, documentado en
 * %TEMP%/opencode/pr3-harness/fixtures.php):
 *
 *  - Una conexión ELEVADA propia (GUCs app.contexto_cargado='true' +
 *    app.rol='admin', alcance de sesión) prepara el escenario: catálogo,
 *    escuela, usuarios y pivotes de rol. Esa conexión NUNCA ejecuta las
 *    afirmaciones: solo crea (y luego borra) los registros que exigen un
 *    contexto que la app no puede tener (el check literal de pc_usuario_insert
 *    solo permite rol='user', así que el admin global se crea en dos pasos:
 *    INSERT rol='user' + UPDATE rol='admin' vía fc_es_admin()).
 *
 *  - La conexión de la app (Database::getConnection()) corre con contexto REAL:
 *    set_config('app.user_id', ...) como en producción (Database.php). Las
 *    afirmaciones ejercen las policies de RLS de verdad; cada test escribe
 *    dentro de una transacción que se REVIERTE en tearDown().
 *
 *  - Todos los registros sembrados llevan el tag "P4T" (nombre/email) y se
 *    borran en tearDownAfterClass(), idempotente por si una corrida anterior
 *    quedó a mitad de camino.
 */
abstract class DbCase extends TestCase
{
    protected static string $adminId = '';
    protected static string $jefeId = '';
    protected static string $regularId = '';
    protected static string $directivoId = '';
    protected static string $areaId = '';
    protected static string $materiaId = '';
    protected static string $escuelaId = '';
    protected static string $cursoId = '';
    protected static string $cursoEscuelaId = '';
    protected static string $cursoEscuela2Id = '';

    private static ?PDO $elevated = null;

    public static function setUpBeforeClass(): void
    {
        self::$elevated = self::conexionElevada();
        self::sembrarFixture();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$elevated !== null) {
            self::limpiarFixture(self::$elevated);
        }
    }

    protected function setUp(): void
    {
        Session::clear();
        Database::reset();
    }

    protected function tearDown(): void
    {
        self::rollback();
    }

    /**
     * Contexto REAL de un usuario: la conexión adopta app.user_id como hace la
     * app en producción y abre una transacción que el test revierte.
     */
    protected function usarComo(string $userId): PDO
    {
        Session::set('user_id', $userId);
        Database::reset();
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        return $pdo;
    }

    /**
     * Contexto REAL anónimo/visitante (sin app.user_id) + transacción.
     */
    protected function anonimo(): PDO
    {
        Session::remove('user_id');
        Database::reset();
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        return $pdo;
    }

    protected static function rollback(): void
    {
        $pdo = Database::getConnection();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Database::reset();
        Session::clear();
    }

    /**
     * Conexión elevada: contexto admin cortocircuitado a nivel de sesión.
     * Solo para SEED/CLEANUP (nunca para afirmar comportamiento).
     */
    private static function conexionElevada(): PDO
    {
        global $configuracion;

        $config = $configuracion;
        $pdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'],
                $config['puerto'],
                $config['basenombre']
            ),
            $config['usuario'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdo->exec("SELECT set_config('app.contexto_cargado', 'true', false)");
        $pdo->exec("SELECT set_config('app.rol', 'admin', false)");

        return $pdo;
    }

    private static function sembrarFixture(): void
    {
        $pdo = self::$elevated;

        foreach (['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'] as $dia) {
            $pdo->prepare('INSERT INTO dia (nombre) VALUES (?) ON CONFLICT (nombre) DO NOTHING')->execute([$dia]);
        }

        // Catálogo compartido (claves únicas → idempotente entre clases).
        self::ins('INSERT INTO area (nombre) VALUES (?) ON CONFLICT (nombre) DO NOTHING RETURNING id', ['P4T Area']);
        self::$areaId = self::releer('SELECT id FROM area WHERE nombre = ?', ['P4T Area']);

        self::ins('INSERT INTO materia (nombre, id_area) VALUES (?, ?) ON CONFLICT (nombre) DO NOTHING RETURNING id', ['P4T Mat', self::$areaId]);
        self::$materiaId = self::releer('SELECT id FROM materia WHERE nombre = ?', ['P4T Mat']);

        self::ins('INSERT INTO curso (nombre) VALUES (?) ON CONFLICT (nombre) DO NOTHING RETURNING id', ['P4T1A']);
        self::ins('INSERT INTO curso (nombre) VALUES (?) ON CONFLICT (nombre) DO NOTHING RETURNING id', ['P4T2B']);
        self::$cursoId = self::releer('SELECT id FROM curso WHERE nombre = ?', ['P4T1A']);
        $curso2Id = self::releer('SELECT id FROM curso WHERE nombre = ?', ['P4T2B']);

        // Escuelas: una principal + 13 para paginación (contra ABSOLUTOS no se
        // afirma: los tests usan comparaciones relativas para no depender de
        // datos ambientales).
        self::ins(
            'INSERT INTO escuela (nombre, numero, sigla, sector, region, distrito, localidad, direccion, codigo_postal, telefono, email)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (nombre) DO NOTHING RETURNING id',
            ['P4T Escuela', 9001, 'EES', '1', 1, 'P4T Distrito', 'La Plata', 'P4T Direccion', '1900', '2210000000', 'escuela@p4t.test']
        );
        self::$escuelaId = self::releer('SELECT id FROM escuela WHERE nombre = ?', ['P4T Escuela']);

        for ($i = 1; $i <= 13; $i++) {
            self::ins(
                'INSERT INTO escuela (nombre, numero, sigla, sector, region, distrito, localidad, direccion, codigo_postal)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (nombre) DO NOTHING RETURNING id',
                [sprintf('P4T Pag %02d', $i), 9100 + $i, 'EES', '0', 1, 'P4T Distrito', 'La Plata', 'P4T Direccion', '1900']
            );
        }

        // Cursos dictados (uq_ce_materia: escuela+curso+materia+anio).
        self::ins(
            'INSERT INTO curso_escuela (id_escuela, id_curso, id_materia, carga_horaria, anio)'
            . ' VALUES (?, ?, ?, ?, ?) ON CONFLICT DO NOTHING RETURNING id',
            [self::$escuelaId, self::$cursoId, self::$materiaId, 4.5, 2026]
        );
        self::$cursoEscuelaId = self::releerCursoEscuela($pdo, self::$escuelaId, self::$cursoId, self::$materiaId);
        self::ins(
            'INSERT INTO curso_escuela (id_escuela, id_curso, id_materia, carga_horaria, anio)'
            . ' VALUES (?, ?, ?, ?, ?) ON CONFLICT DO NOTHING RETURNING id',
            [self::$escuelaId, $curso2Id, self::$materiaId, 3.0, 2026]
        );
        self::$cursoEscuela2Id = self::releerCursoEscuela($pdo, self::$escuelaId, $curso2Id, self::$materiaId);

        // Usuarios del fixture (rol 'user' en el INSERT por pc_usuario_insert).
        self::$adminId = self::crearUsuario('P4T Admin', 'p4t.admin@p4t.test');
        self::$jefeId = self::crearUsuario('P4T Jefe', 'p4t.jefe@p4t.test');
        self::$regularId = self::crearUsuario('P4T Regular', 'p4t.regular@p4t.test');
        self::$directivoId = self::crearUsuario('P4T Directivo', 'p4t.directivo@p4t.test');

        // El admin global se promueve en dos pasos: pc_usuario_insert exige
        // rol='user' (literal); el UPDATE pasa por fc_es_admin() (true en la
        // conexión elevada) y deja rol='admin' de verdad.
        $pdo->prepare('UPDATE usuario SET rol = ? WHERE id = ?')
            ->execute(['admin', self::$adminId]);

        // Pivotes por escuela (jefe exige id_area por chk_area_rol).
        $pdo->prepare('INSERT INTO escuela_usuario (id_escuela, id_usuario, rol, id_area) VALUES (?, ?, ?, ?)')
            ->execute([self::$escuelaId, self::$jefeId, 'jefe', self::$areaId]);
        $pdo->prepare('INSERT INTO escuela_usuario (id_escuela, id_usuario, rol, id_area) VALUES (?, ?, ?, ?)')
            ->execute([self::$escuelaId, self::$directivoId, 'directivo', null]);
    }

    private static function crearUsuario(string $nombre, string $email): string
    {
        $pdo = self::$elevated;
        $id = self::ins(
            'INSERT INTO usuario (nombre, email, pass, rol) VALUES (?, ?, ?, ?) ON CONFLICT (email) DO NOTHING RETURNING id',
            [$nombre, $email, password_hash('p4t-clave', PASSWORD_DEFAULT), 'user']
        );
        if ($id === '') {
            $id = self::releer('SELECT id FROM usuario WHERE email = ?', [$email]);
        }

        return $id;
    }

    private static function releerCursoEscuela(PDO $pdo, string $escuelaId, string $cursoId, string $materiaId): string
    {
        $statement = $pdo->prepare(
            'SELECT id FROM curso_escuela WHERE id_escuela = ? AND id_curso = ? AND id_materia = ? AND anio = 2026'
        );
        $statement->execute([$escuelaId, $cursoId, $materiaId]);

        return (string) $statement->fetchColumn();
    }

    /** @return string inserted uuid ('' si ON CONFLICT no insertó) */
    private static function ins(string $sql, array $params): string
    {
        $statement = self::$elevated->prepare($sql);
        $statement->execute($params);
        $id = $statement->fetchColumn();

        return $id !== false ? (string) $id : '';
    }

    private static function releer(string $sql, array $params): string
    {
        $statement = self::$elevated->prepare($sql);
        $statement->execute($params);

        return (string) $statement->fetchColumn();
    }

    private static function limpiarFixture(PDO $pdo): void
    {
        // Orden respeta las FK (sin CASCADE entre catálogo y pivotes).
        $pdo->exec("DELETE FROM curso_escuela_dia WHERE id_curso_escuela IN (SELECT ce.id FROM curso_escuela ce JOIN escuela e ON e.id = ce.id_escuela WHERE e.nombre LIKE 'P4T %')");
        $pdo->exec("DELETE FROM curso_profesor WHERE id_curso_escuela IN (SELECT ce.id FROM curso_escuela ce JOIN escuela e ON e.id = ce.id_escuela WHERE e.nombre LIKE 'P4T %')");
        $pdo->exec("DELETE FROM curso_escuela WHERE id_escuela IN (SELECT id FROM escuela WHERE nombre LIKE 'P4T %')");
        $pdo->exec("DELETE FROM escuela_usuario WHERE id_escuela IN (SELECT id FROM escuela WHERE nombre LIKE 'P4T %') OR id_usuario IN (SELECT id FROM usuario WHERE email LIKE '%@p4t.test')");
        $pdo->exec("DELETE FROM profesor WHERE nombre LIKE 'P4T %'");
        $pdo->exec("DELETE FROM escuela WHERE nombre LIKE 'P4T %'");
        $pdo->exec("DELETE FROM usuario WHERE email LIKE '%@p4t.test'");
        $pdo->exec("DELETE FROM materia WHERE nombre LIKE 'P4T %'");
        $pdo->exec("DELETE FROM curso WHERE nombre LIKE 'P4T %'");
        $pdo->exec("DELETE FROM area WHERE nombre LIKE 'P4T %'");
    }
}