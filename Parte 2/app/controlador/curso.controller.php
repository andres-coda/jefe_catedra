<?php
require_once("app/controlador/controller.php");
require_once("app/modelo/curso_escuela.modelo.php");
require_once("app/modelo/materia.modelo.php");
require_once("app/modelo/area.modelo.php");
require_once("app/vista/cursoEscuela.view.php");
class CursoEscuelaController extends Controller
{
  private $materiaModelo;
  private $areaModelo;
  public function __construct()
  {
    $this->model = new CursoEscuelaModelo();
    $this->view = new CursoEscuelaView();
    $this->materiaModelo = new MateriaModelo();
    $this->areaModelo = new AreaModelo();
  }

  public function crearCurso($idEscuela, $nombreArea = null)
  {
    $this->view->crearCurso($idEscuela, null, null, $nombreArea);
  }

  public function mostrarCursos($idEscuela)
  {
    
    $cursos = $this->model->obtenerCursoEscuelaPorId($idEscuela);
    $nuevoCurso = BASE_URL . 'escuela/' . $idEscuela . '/curso/nuevo';
    
    if (empty($cursos)) {
      $this->view->mostrarElementosVacio($nuevoCurso);
      return;
    }
    $this->view->mostrarCursos($nuevoCurso, $cursos);
  }

  public function enviarCrearCurso($idEscuela)
  {
    $datos = (object) [
      'nombre' => trim($_POST['nombre'] ?? ''),
      'area' => trim($_POST['area'] ?? ''),
      'materia' => trim($_POST['materia'] ?? ''),
      'carga_horaria' => str_replace(',', '.', trim($_POST['carga_horaria'] ?? '')),
      'anio' => trim($_POST['anio'] ?? ''),
    ];

    $errores = (object) [];

    if (empty($datos->nombre)) {
      $errores->nombre = "Requiere un nombre para crear el curso";
    }

    if (empty($datos->area)) {
      $errores->area = "Requiere una area para crear el curso";
    }

    if (empty($datos->materia)) {
      $errores->materia = "Requiere una materia para crear el curso";
    }

    if (!empty($datos->carga_horaria)) {
      if (!preg_match('/^\d+(\.\d{1,2})?$/', $datos->carga_horaria)) {
        $errores->carga_horaria = "La carga horaria debe ser un número válido";
      }
    }

    if (empty($datos->anio)) {
      $datos->anio = date('Y');
    }

    if (!preg_match('/^20\d{2}$/', $datos->anio)) {
      $errores->anio = "El año debe tener el formato 20XX";
    }

    if (!empty((array) $errores)) {
      $this->view->crearCurso($datos, $errores);
      return;
    }

    $cursoXnombre = $this->model->obtenerCursoPorNombre($datos->nombre);

    if (empty($cursoXnombre)) {
      $cursoXnombre = $this->model->insertarCurso($datos->nombre);
    }

    $area = $this->areaModelo->obtenerAreaPorNombre($datos->area);

    if (empty($area)) {
      $area = $this->areaModelo->insertarArea($datos->area);
    }

    $materia = $this->materiaModelo->obtenerMateriaPorNombre($datos->materia);

    if (empty($materia)) {
      $materia = $this->materiaModelo->insertarMateria($datos->materia, $area->id);
    }


    $curso = $this->model->insertarCursoEscuela($idEscuela, $cursoXnombre->id, $materia->id, $datos->carga_horaria, $datos->anio);


    if (!empty($curso)) {
      $this->mostrarCursos($idEscuela);
    }
  }
}