<?php
require_once("app/controlador/controller.php");
require_once("app/modelo/area.modelo.php");
require_once("app/modelo/area.modelo.php");
require_once("app/vista/area.view.php");
class AreaController extends Controller
{
  public function __construct()
  {
    $this->model = new AreaModelo();
    $this->view = new AreaView();
  }

  public function crearArea()
  {
    $this->view->crearArea();
  }

  public function mostrarAreas($idEscuela)
  {
    $areas = $this->model->obtenerAreasPorIdEscuela($idEscuela);
    $rutaBase = BASE_URL . 'escuela/' . $idEscuela . '/area';
    $nuevaArea = $rutaBase . '/nueva';
    if (empty($areas)) {
      $this->view->mostrarElementosVacio($nuevaArea);
      return;
    }
    $this->view->mostrarAreas($areas, $nuevaArea, $rutaBase);
  }

  public function mostrarArea($nombreArea)
  {
    $nombreMateriaDecodificado = urldecode($nombreArea);
    $materias = $this->model->obtenerMateriasPorNombreArea($nombreMateriaDecodificado);
    $materiaNueva = $nombreArea . '/nueva';
    if (empty($materias)) {
      $this->view->mostrarElementosVacio($materiaNueva);
      return;
    }
    $this->view->mostrarArea($materias, $materiaNueva, $nombreMateriaDecodificado);
  }

  public function enviarCrearArea()
  {
    $datos = (object) [
      'nombre' => trim($_POST['nombre'] ?? ''),
    ];

    $errores = (object) [];

    if (empty($datos->nombre)) {
      $errores->nombre = "Requiere un nombre para crear el area";
    }

    if (!empty((array) $errores)) {
      $this->view->crearArea($datos, $errores);
      return;
    }

    $areaXnombre = $this->model->obtenerAreaPorNombre($datos->nombre);

    if(!empty($areaXnombre)) {
      $this->mostrarAreas($areaXnombre);
      return;
    }

    $areaXnombre = $this->model->insertarArea($datos->nombre);

    if (!empty($areaXnombre)) {
      $this->mostrarAreas($areaXnombre);
    }
  }
}