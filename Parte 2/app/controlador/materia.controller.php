<?php
require_once("app/controlador/controller.php");
require_once("app/modelo/materia.modelo.php");
require_once("app/modelo/area.modelo.php");
require_once("app/vista/materia.view.php");
class MateriaController extends Controller
{
  private $areaModelo;
  public function __construct()
  {
    $this->model = new MateriaModelo();
    $this->view = new MateriaView();
    $this->areaModelo = new AreaModelo();
  }

  public function crearMateria()
  {
    $this->view->crearMateria();
  }

  public function mostrarMaterias($idEscuela)
  {
    $materias = $this->model->obtenerMateriaPorIdEscuela($idEscuela);
    if (empty($materias)) {
      $nuevoMateria = BASE_URL . 'escuela/materia/nueva';
      $this->view->mostrarElementosVacio($nuevoMateria);
      return;
    }
    $this->view->mostrarMaterias($materias);
  }

  public function enviarCrearMateria()
  {
    $datos = (object) [
      'nombre' => trim($_POST['nombre'] ?? ''),
      'area' => trim($_POST['area'] ?? ''),
    ];

    $errores = (object) [];

    if (empty($datos->nombre)) {
      $errores->nombre = "Requiere un nombre para crear el materia";
    }

    if (empty($datos->area)) {
      $errores->area = "Requiere un area para crear el materia";
    }

    if (!empty((array) $errores)) {
      $this->view->crearMateria($datos, $errores);
      return;
    }

    $materiaXnombre = $this->model->obtenerMateriaPorNombre($datos->nombre);

    if(!empty($materiaXnombre)) {
      return $materiaXnombre;
    }

    $area = $this->areaModelo->obtenerAreaPorNombre($datos->area);

    if (empty($area)) {
      $area = $this->areaModelo->insertarArea($datos->area);
    }

    $materiaXnombre = $this->model->insertarMateria($datos->nombre, $area->id);

    if (!empty($materiaXnombre)) {
      $this->mostrarMaterias($materiaXnombre);
    }
  }
}