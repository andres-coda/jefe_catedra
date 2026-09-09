<?php
require_once('app/modelo/modelo.php');

class CursoEscuelaModelo extends Modelo
{

  public function obtenerCursoPorNombre($nombre)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          SELECT id FROM curso 
          WHERE nombre = ?
        ');
      $sentencia->execute([$nombre]);
      $curso = $sentencia->fetch(PDO::FETCH_OBJ);
      return $curso;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function insertarCurso($nombre)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO curso (nombre) 
          VALUES (?)
          RETURNING *
        ');
      $sentencia->execute([$nombre]);

      $curso = $sentencia->fetch(PDO::FETCH_OBJ);

      return $curso;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerCursoEscuelaPorId($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM vw_curso_completo 
          WHERE id_curso_escuela = ?
        ');
      $sentencia->execute([$id]);
      $curso = $sentencia->fetch(PDO::FETCH_OBJ);
      return $curso;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerMateriaPorNombre($nombre)
  {
    $sentencia = $this->getPdo()->prepare('
        SELECT *
        FROM materia
        WHERE nombre = ?
    ');

    $sentencia->execute([$nombre]);

    return $sentencia->fetch(PDO::FETCH_OBJ);
  }

  public function insertarCursoEscuela($id_escuela, $id_curso, $id_materia, $carga_horaria, $anio)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO curso_escuela (id_escuela, id_curso, id_materia, carga_horaria, anio) 
          VALUES (?,?,?,?,?)
          RETURNING *
        ');
      $sentencia->execute([$id_escuela, $id_curso, $id_materia, $carga_horaria, $anio]);

      $curso_escuela = $sentencia->fetch(PDO::FETCH_OBJ);

      return $curso_escuela;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function editarCursoEscuela($id, $nombreCursoEscuela)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        UPDATE curso_escuela SET nombre = ? 
        WHERE id = ?
        RETURNING *
      ');
      $sentencia->execute([$nombreCursoEscuela, $id]);
      $curso_escuela = $sentencia->fetch(PDO::FETCH_OBJ);
      return $curso_escuela;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function delete($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        DELETE FROM curso_escuela WHERE id = ?
      ');
      $sentencia->execute([$id]);

      return true;
    } catch (\Throwable $th) {
      return false;
    }
  }
}