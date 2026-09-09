<?php
require_once("app/controlador/controller.php");
require_once("app/modelo/curso_escuela.modelo.php");
require_once("app/modelo/materia.modelo.php");
require_once("app/vista/cursoEscuela.view.php");
class CursoEscuelaController extends Controller
{
  private $materiaModelo;
  public function __construct()
  {
    $this->model = new CursoEscuelaModelo();
    $this->view = new CursoEscuelaView();
    $this->materiaModelo = new MateriaModelo();
  }

  public function crearCurso()
  {
    $this->view->crearCurso();
  }

  public function mostrarCursos($idEscuela)
  {
    $cursos = $this->model->obtenerCursoEscuelaPorId($idEscuela);
    if(empty($cursos)){
      $nuevoCurso = BASE_URL . 'escuela/cursos/nuevo/<?= $idEscuela ?>';
      $this->view->mostrarElementosVacio($nuevoCurso);
      return;
    }
    $this->view->mostrarCursos($cursos);
  }

  public function enviarCrearCurso($idEscuela)
  {
    $datos = (object) [
      'nombre' => trim($_POST['nombre'] ?? ''),
      'materia' => trim($_POST['materia'] ?? ''),
      'carga_horaria' => str_replace(',', '.', trim($_POST['carga_horaria'] ?? '')),
      'anio' => trim($_POST['anio'] ?? ''),
    ];

    $errores = (object) [];

    if (empty($datos->nombre)) {
      $errores->nombre = "Requiere un nombre para crear el curso";
    }

    if (empty($datos->materia)) {
      $errores->materia = "Requiere una materia para crear el curso";
    } 

    if (!empty($datos->carga_horaria)) {
      if (!preg_match('/^\d+,\d{2}$/', $datos->carga_horaria)) {
        $errores->carga_horaria = "La carga horaria debe tener el formato 00,00";
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

    if(empty($cursoXnombre)) {
      $cursoXnombre = $this->model->insertarCurso($datos->nombre);
    }

    $materia = $this->materiaModelo->obtenerMateriaPorNombre($datos->nombre);

    if(empty($cursoXnombre)) {
      $cursoXnombre = $this->materiaModelo->insertarMateria($datos->nombre);
    }


    $curso = $this->model->insertarCursoEscuela($idEscuela, $cursoXnombre->id,);


    if (!empty($curso)) {
      $this->mostrarCursos($curso);
    }
  }
}