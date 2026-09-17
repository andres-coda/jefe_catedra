<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Request;

/**
 * CursoEscuela (tabla curso_escuela): oferta concreta (curso + materia + carga
 * horaria) dictada en una escuela. La lectura por escuela se restringe por RLS
 * (pc_curso_escuela_select con id_escuela = school_id en el contexto).
 *
 * Nota de schema aplicado: el diseño original preveía id_profesor en esta tabla;
 * el schema aplicado (18 tablas) modela profesores por escuela (tabla profesor)
 * con asignaciones por curso (curso_profesor), así que aquí no hay id_profesor.
 */
final class CursoEscuela
{
    private const TABLE = 'curso_escuela';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(string $id): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, id_escuela, id_curso, id_materia, carga_horaria, anio'
            . ' FROM ' . self::TABLE . ' WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Escuela a la que pertenece un curso de escuela, o null si no existe.
     */
    public static function escuelaIdDe(string $id): ?string
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id_escuela FROM ' . self::TABLE . ' WHERE id = ?'
        );
        $statement->execute([$id]);
        $escuela = $statement->fetchColumn();

        return $escuela !== false ? (string) $escuela : null;
    }

    /**
     * Curso detallado para la ficha: datos del curso, materia y área.
     *
     * @return array<string, mixed>|null
     */
    public static function detalleById(string $id): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT ce.id, ce.id_escuela, ce.id_curso, ce.id_materia, ce.carga_horaria, ce.anio,'
            . ' c.nombre AS curso, m.nombre AS materia, a.id AS id_area, a.nombre AS area'
            . ' FROM ' . self::TABLE . ' ce'
            . ' JOIN curso c ON c.id = ce.id_curso'
            . ' JOIN materia m ON m.id = ce.id_materia'
            . ' JOIN area a ON a.id = m.id_area'
            . ' WHERE ce.id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Cursos de una escuela con filtros opcionales (día, hora, profesor).
     *
     * @param array<string, string|null> $filtros claves: dia, hora, profesor
     * @return array<int, array<string, mixed>>
     */
    public static function listByEscuela(string $escuelaId, array $filtros = []): array
    {
        $sql = 'SELECT ce.id, ce.id_escuela, ce.carga_horaria, ce.anio,'
            . ' c.id AS id_curso, c.nombre AS curso, m.id AS id_materia, m.nombre AS materia,'
            . ' a.id AS id_area, a.nombre AS area'
            . ' FROM ' . self::TABLE . ' ce'
            . ' JOIN curso c ON c.id = ce.id_curso'
            . ' JOIN materia m ON m.id = ce.id_materia'
            . ' JOIN area a ON a.id = m.id_area'
            . ' WHERE ce.id_escuela = ?';
        $params = [$escuelaId];

        if (($filtros['dia'] ?? null) !== null || ($filtros['hora'] ?? null) !== null) {
            $sql .= ' AND EXISTS ('
                . ' SELECT 1 FROM curso_escuela_dia ced'
                . ' WHERE ced.id_curso_escuela = ce.id';
            if (($filtros['dia'] ?? null) !== null) {
                $sql .= ' AND ced.id_dia = ?';
                $params[] = (string) $filtros['dia'];
            }
            if (($filtros['hora'] ?? null) !== null) {
                $sql .= ' AND ced.hora_entrada <= ? AND ced.hora_salida > ?';
                $params[] = (string) $filtros['hora'];
                $params[] = (string) $filtros['hora'];
            }
            $sql .= ' )';
        }

        if (($filtros['profesor'] ?? null) !== null) {
            // Public read path for the professor filter: curso_profesor is
            // staff-only RLS (pc_curso_profesor_select), so a direct EXISTS
            // subquery sees zero rows for anonymous visitors. The SECURITY
            // DEFINER helper fc_curso_profesor_activo() checks the active
            // assignment as the table owner and exposes only a boolean for the
            // given (curso_escuela, profesor) pair — RLS everywhere else stays
            // intact and no staff-only column is leaked.
            $sql .= ' AND fc_curso_profesor_activo(ce.id, ?)';
            $params[] = (string) $filtros['profesor'];
        }

        $sql .= ' ORDER BY a.nombre, m.nombre, c.nombre';

        $statement = Database::getConnection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * Cursos de una materia dentro de una escuela (vista de materias).
     *
     * @param array<string, string|null> $filtros claves: dia, hora, profesor
     * @return array<int, array<string, mixed>>
     */
    public static function listByMateria(string $escuelaId, string $materiaId, array $filtros = []): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT ce.id, m.id AS id_materia, m.nombre AS materia,'
            . ' c.id AS id_curso, c.nombre AS curso, ce.carga_horaria, ce.anio'
            . ' FROM ' . self::TABLE . ' ce'
            . ' JOIN curso c ON c.id = ce.id_curso'
            . ' JOIN materia m ON m.id = ce.id_materia'
            . ' WHERE ce.id_escuela = ? AND ce.id_materia = ?'
            . ' ORDER BY c.nombre'
        );
        $statement->execute([$escuelaId, $materiaId]);

        return $statement->fetchAll();
    }

    /**
     * Agrupa una lista plana de cursos por área y materia (vista de áreas).
     *
     * @param array<int, array<string, mixed>> $cursos
     * @return array<int, array<string, mixed>>
     */
    public static function agruparPorArea(array $cursos): array
    {
        $areas = [];
        foreach ($cursos as $curso) {
            $idArea = (string) $curso['id_area'];
            if (!isset($areas[$idArea])) {
                $areas[$idArea] = ['id_area' => $idArea, 'area' => (string) $curso['area'], 'materias' => []];
            }
            $idMateria = (string) $curso['id_materia'];
            if (!isset($areas[$idArea]['materias'][$idMateria])) {
                $areas[$idArea]['materias'][$idMateria] = [
                    'id_materia' => $idMateria,
                    'materia' => (string) $curso['materia'],
                    'cursos' => [],
                ];
            }
            $areas[$idArea]['materias'][$idMateria]['cursos'][] = $curso;
        }

        $resultado = [];
        foreach ($areas as $area) {
            $area['materias'] = array_values($area['materias']);
            $resultado[] = $area;
        }

        return $resultado;
    }

    /**
     * Crea una oferta curso+materia en una escuela y devuelve la fila.
     *
     * @throws \RuntimeException si la combinación (escuela, curso, materia, anio)
     *                           ya existe (message 'curso_escuela_duplicada')
     *
     * @return array<string, mixed>
     */
    public static function create(string $escuelaId, string $cursoId, string $materiaId, ?float $cargaHoraria, ?int $anio): array
    {
        try {
            $statement = Database::getConnection()->prepare(
                'INSERT INTO ' . self::TABLE . ' (id_escuela, id_curso, id_materia, carga_horaria, anio)'
                . ' VALUES (?, ?, ?, ?, ?)'
                . ' RETURNING id, id_escuela, id_curso, id_materia, carga_horaria, anio'
            );
            $statement->execute([$escuelaId, $cursoId, $materiaId, $cargaHoraria, $anio]);
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23505') { // unique_violation (uq_ce_materia)
                throw new \RuntimeException('curso_escuela_duplicada');
            }
            throw $exception;
        }

        $row = $statement->fetch();
        if ($row === false) {
            throw new \RuntimeException('No se pudo recuperar el curso dictado creado.');
        }

        return $row;
    }

    /**
     * Filtros de navegación (por el momento solo soporta query) que no
     * pretenden validar la existencia de los valores: el SQL los resuelve.
     * Se mantiene aquí para evitar duplicar la extracción en cada controlador.
     *
     * @return array{dia: string|null, hora: string|null, profesor: string|null}
     */
    public static function filtrosDe(Request $request): array
    {
        return [
            'dia' => self::uuidDeQuery($request, 'dia'),
            'hora' => self::horaDeQuery($request),
            'profesor' => self::uuidDeQuery($request, 'profesor'),
        ];
    }

    private static function uuidDeQuery(Request $request, string $clave): ?string
    {
        $valor = (string) $request->query($clave, '');
        if ($valor === '') {
            return null;
        }

        return preg_match('~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~', $valor) === 1
            ? $valor
            : null;
    }

    private static function horaDeQuery(Request $request): ?string
    {
        $hora = trim((string) $request->query('hora', ''));
        if ($hora === '') {
            return null;
        }

        return preg_match('~^\d{1,2}:\d{2}(:\d{2})?$~', $hora) === 1 ? $hora : null;
    }
}