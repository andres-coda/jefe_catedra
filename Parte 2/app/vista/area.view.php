<?php
require_once('app/vista/view.php');
class AreaView extends View
{
  public function crearArea($datos = null, $err = null)
  {
    $formulario = (object) [
      'titulo' => 'Crear area',
      'accion' => 'crearArea',
      'borrar' => 'Limpiar',
      'aceptar' => 'Crear Area',

      'datos' => [
        (object) [
          'id' => 'nombre',
          'label' => 'Nombre de la nueva area',
          'tipo' => 'text',
          'requerido' => true,
          'valor' => $datos->nombre ?? '',
          'error' => $err->nombre ?? null
        ],
      ]
    ];

    $this->maquetaFormulario($formulario);
  }

  public function mostrarAreas($areas, $nuevaArea, $selectArea = null)
  {
    $card = './app/template/area.card.phtml';
    $this->mostrarElementos($areas, $card, $nuevaArea, 'Areas', $selectArea);
  }

  public function mostrarArea($materias, $nuevaMateria, $nombreArea)
  {
    $card = './app/template/materia.card.phtml';
    $this->mostrarElementos($materias, $card, $nuevaMateria, $nombreArea);
  }
}