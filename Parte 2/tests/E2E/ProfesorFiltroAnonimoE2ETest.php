<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Controllers\EscuelaController;
use App\Models\CursoProfesor;
use PDO;
use Tests\Support\DbCase;

/**
 * Regression (verification CRITICAL 2): the professor filter must work for
 * anonymous visitors. curso_profesor is staff-only RLS
 * (pc_curso_profesor_select), so the filter now goes through the SECURITY
 * DEFINER helper fc_curso_profesor_activo() instead of a direct EXISTS
 * subquery. This test proves:
 *  - anonymous sees the course for an ACTIVE assignment and the empty state
 *    for a ceased and a nonexistent professor;
 *  - the staff read path keeps working (jefe);
 *  - direct anonymous reads of curso_profesor/profesor stay blocked (no RLS
 *    widening).
 */
final class ProfesorFiltroAnonimoE2ETest extends DbCase
{
    private static string $profesorActivoId = '';
    private static string $profesorCesadoId = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::sembrarProfesores();
    }

    public function testAnonimoFiltraPorProfesorActivoYProfesorInexistente(): void
    {
        // Anonymous visitor: active assignment -> the course is listed and the
        // course without a matching assignment is excluded.
        $this->anonimo();

        $html = self::cuerpo($this->vistaCursos('profesor', self::$profesorActivoId));
        self::assertStringContainsString('P4T1A', $html, 'Anonymous sees the course of the active professor.');
        self::assertStringNotContainsString('P4T2B', $html, 'The other course has no matching assignment.');

        // Ceased assignment (fecha_cese NOT NULL) -> empty state.
        $html = self::cuerpo($this->vistaCursos('profesor', self::$profesorCesadoId));
        self::assertStringContainsString(
            'No hay cursos que coincidan con los filtros.',
            $html,
            'A ceased professor no longer matches courses.'
        );

        // Nonexistent professor -> empty state.
        $html = self::cuerpo($this->vistaCursos('profesor', '00000000-0000-0000-0000-000000000000'));
        self::assertStringContainsString(
            'No hay cursos que coincidan con los filtros.',
            $html,
            'A nonexistent professor yields the empty state.'
        );

        // Staff path keeps working: jefe still matches the same course and can
        // read the staff-only assignment list.
        $this->usarComo(self::$jefeId);

        $html = self::cuerpo($this->vistaCursos('profesor', self::$profesorActivoId));
        self::assertStringContainsString('P4T1A', $html, 'The jefe filter matches the same course.');

        $asignaciones = CursoProfesor::listByCurso(self::$cursoEscuelaId);
        self::assertContains(self::$profesorActivoId, array_column($asignaciones, 'id_profesor'));

        // RLS intact: anonymous direct reads of the staff-only tables still
        // return zero rows.
        $this->anonimo();

        $count = (int) Database::getConnection()->query('SELECT count(*) FROM curso_profesor')->fetchColumn();
        self::assertSame(0, $count, 'curso_profesor stays staff-only (pc_curso_profesor_select).');

        $count = (int) Database::getConnection()->query('SELECT count(*) FROM profesor')->fetchColumn();
        self::assertSame(0, $count, 'profesor stays staff-only (pc_profesor_select).');
    }

    /**
     * Renders the courses view of the school ficha with a filter value, the
     * same controller/request shape the public /escuelas/{id} route uses.
     */
    private function vistaCursos(string $filtro, string $valor): Response
    {
        $request = new Request(
            'GET',
            '/escuelas/' . self::$escuelaId,
            [$filtro => $valor],
            [],
            [],
            ['id' => self::$escuelaId]
        );

        return (new EscuelaController())->show($request);
    }

    /**
     * Seeds the P4T professor fixture (active + ceased assignments) through an
     * elevated context, following the DbCase pattern (only seeding runs there;
     * assertions always use the real app context). P4T-tagged rows are removed
     * by DbCase::tearDownAfterClass().
     */
    private static function sembrarProfesores(): void
    {
        $pdo = self::conexionElevadaLocal();

        $insertProfesor = $pdo->prepare(
            'INSERT INTO profesor (nombre, email, id_escuela) VALUES (?, ?, ?)'
            . ' ON CONFLICT (id_escuela, email) DO NOTHING RETURNING id'
        );
        $insertProfesor->execute(['P4T Prof Activo', 'p4t.prof.activo@p4t.test', self::$escuelaId]);
        self::$profesorActivoId = self::releerProfesor($pdo, self::$escuelaId, 'p4t.prof.activo@p4t.test');

        $insertProfesor->execute(['P4T Prof Cesado', 'p4t.prof.cesado@p4t.test', self::$escuelaId]);
        self::$profesorCesadoId = self::releerProfesor($pdo, self::$escuelaId, 'p4t.prof.cesado@p4t.test');

        // Active assignment (fecha_cese NULL) for the first professor.
        $pdo->prepare(
            'INSERT INTO curso_profesor (id_curso_escuela, id_profesor, fecha_ingreso)'
            . ' VALUES (?, ?, ?)'
            . ' ON CONFLICT (id_curso_escuela, id_profesor)'
            . ' DO UPDATE SET fecha_cese = NULL, fecha_ingreso = EXCLUDED.fecha_ingreso'
        )->execute([self::$cursoEscuelaId, self::$profesorActivoId, '2026-03-01']);

        // Ceased assignment for the second professor.
        $pdo->prepare(
            'INSERT INTO curso_profesor (id_curso_escuela, id_profesor, fecha_ingreso, fecha_cese)'
            . ' VALUES (?, ?, ?, ?)'
            . ' ON CONFLICT (id_curso_escuela, id_profesor)'
            . ' DO UPDATE SET fecha_cese = EXCLUDED.fecha_cese, fecha_ingreso = EXCLUDED.fecha_ingreso'
        )->execute([self::$cursoEscuelaId, self::$profesorCesadoId, '2026-01-15', '2026-06-30']);
    }

    private static function conexionElevadaLocal(): PDO
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

    private static function releerProfesor(PDO $pdo, string $escuelaId, string $email): string
    {
        $statement = $pdo->prepare('SELECT id FROM profesor WHERE id_escuela = ? AND email = ?');
        $statement->execute([$escuelaId, $email]);

        return (string) $statement->fetchColumn();
    }

    private static function cuerpo(Response $response): string
    {
        ob_start();
        $response->send();

        return (string) ob_get_clean();
    }
}