<?php

require_once('app/vista/view.php');
class EscuelaView extends View
{
  public function crearEscuela($datos = null, $err = null)
  {
    $formulario = (object) [
      'titulo' => 'Crear escuela',
      'accion' => 'escuela/crearEscuela',
      'borrar' => 'Limpiar',
      'aceptar' => 'Crear Escuela',

      'datos' => [
        (object) [
          'id' => 'nombre',
          'label' => 'Nombre de la nueva escuela',
          'tipo' => 'text',
          'requerido' => true,
          'valor' => $datos->nombre ?? '',
          'error' => $err->nombre ?? null
        ]
      ]
    ];

    $this->maquetaFormulario($formulario);
  }

  public function mostrarEscuelas($escuelas){
    $card = './app/template/escuela.card.phtml';
    $nuevaEscuela = BASE_URL . 'escuela/nueva';
    $this->mostrarElementos($escuelas, $card, $nuevaEscuela, 'Escuelas');
  }
}

