<?php
require_once('app/vista/view.php');
class CursoEscuelaView extends View
{
  public function crearCurso($idEscuela, $datos = null, $err = null, $nombreArea = null)
  {
    var_dump($nombreArea);
    $formulario = (object) [
      'titulo' => 'Crear curso',
      'accion' => 'crearCurso',
      'borrar' => 'Limpiar',
      'aceptar' => 'Crear Curso',

      'datos' => [
        (object) [
          'id' => 'nombre',
          'label' => 'Nombre del nuevo curso',
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
          'valor' => $datos->area ?? ($nombreArea ? urldecode($nombreArea) : ''),
          'error' => $err->area ?? null
        ],
        (object) [
          'id' => 'materia',
          'label' => 'Nombre de la materia',
          'tipo' => 'text',
          'requerido' => true,
          'valor' => $datos->materia ?? '',
          'error' => $err->materia ?? null
        ],
        (object) [
          'id' => 'carga_horaria',
          'label' => 'Carga horaria',
          'tipo' => 'text',
          'requerido' => false,
          'valor' => $datos->carga_horaria ?? '',
          'error' => $err->carga_horaria ?? null
        ],
        (object) [
          'id' => 'anio',
          'label' => 'Año',
          'tipo' => 'text',
          'requerido' => true,
          'valor' => $datos->anio ?? '',
          'error' => $err->anio ?? null
        ]
      ]
    ];

    $this->maquetaFormulario($formulario);
  }

  public function mostrarCursos($nuevoCurso, $cursos)
  {
    $card = './app/template/curso.card.phtml';
    $this->mostrarElementos($cursos, $card, $nuevoCurso, 'Cursos');
  }
}