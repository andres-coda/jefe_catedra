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

  public function crearEscuela(){
    $this->view->crearEscuela();
  }
}