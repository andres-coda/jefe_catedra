<?php
require_once('app/vista/view.php');
class MateriaView extends View
{
  public function crearMateria($datos = null, $err = null)
  {
    $formulario = (object) [
      'titulo' => 'Crear materia',
      'accion' => 'crearMateria',
      'borrar' => 'Limpiar',
      'aceptar' => 'Crear Materia',

      'datos' => [
        (object) [
          'id' => 'nombre',
          'label' => 'Nombre de la nueva materia',
          'tipo' => 'text',
          'requerido' => true,
          'valor' => $datos->nombre ?? '',
          'error' => $err->nombre ?? null
        ],
        (object) [
          'id' => 'area',
          'label' => 'Nombre del area',
          'tipo' => 'text',
          'requerido' => true,
          'valor' => $datos->area ?? '',
          'error' => $err->area ?? null
        ],
      ]
    ];

    $this->maquetaFormulario($formulario);
  }

  public function mostrarMaterias($materias)
  {
    $card = './app/template/materia.card.phtml';
    $nuevoMateria = BASE_URL . 'materia/nuevo';
    $this->mostrarElementos($materias, $card, $nuevoMateria, 'Materias');
  }
}