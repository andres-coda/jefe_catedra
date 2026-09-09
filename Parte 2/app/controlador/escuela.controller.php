<?php
require_once("app/controlador/controller.php");
require_once("app/modelo/escuela.modelo.php");
require_once("app/vista/escuela.view.php");
class EscuelaController extends Controller
{
  public function __construct()
  {
    $this->model = new EscuelaModelo();
    $this->view = new EscuelaView();
  }

  public function crearEscuela()
  {
    $this->view->crearEscuela();
  }

  public function mostrarEscuela($escuela)
  {
    $this->view->crearEscuela();
  }

  public function mostrarEscuelas()
  {
    $escuelas = $this->model->obtenerEscuela();
    $this->view->mostrarEscuelas($escuelas);
  }

  public function enviarCrearEscuela()
  {
    $datos = (object) [
      'nombre' => trim($_POST['nombre'] ?? '')
    ];

    $errores = (object) [];

    if (empty($datos->nombre)) {
      $errores->nombre = "Requiere un nombre para la escuela";
    }

    if (!empty((array) $errores)) {
      $this->view->crearEscuela($datos, $errores);
      return;
    }

    $escuela = $this->model->insertarEscuela($datos->nombre);


    if (!empty($escuela)) {
      $this->mostrarEscuela($escuela);
    }
  }
}