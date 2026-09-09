<?php
require_once('app/vista/view.php');
class CursoEscuelaView extends View
{
  public function crearCurso($datos = null, $err = null)
  {
    $formulario = (object) [
      'titulo' => 'Crear curso',
      'accion' => 'curso/crearCurso',
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

  public function mostrarCursos($cursos)
  {
    $card = './app/template/curso.card.phtml';
    $nuevoCurso = BASE_URL . 'curso/nuevo';
    $this->mostrarElementos($cursos, $card, $nuevoCurso);
  }
}