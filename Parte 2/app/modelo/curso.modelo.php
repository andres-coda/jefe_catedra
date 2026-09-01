<?php
require_once('app/modelos/Modelo.php');

class CursoModelo extends Modelo
{

  public function obtenerCursos()
  {
    try {

      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM vw_curso 
          ORDER BY escula, curso
        ');
      $sentencia->execute();
      $cursos = $sentencia->fetchAll(PDO::FETCH_OBJ);

      return $cursos;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerCursoPorId($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM vw_curso_completo 
          WHERE id_curso = ?
        ');
      $sentencia->execute([$id]);
      $curso = $sentencia->fetch(PDO::FETCH_OBJ);
      return $curso;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function insertarCurso($nombreCurso)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO curso (nombre) VALUES (?)
          RETURNING *
        ');
      $sentencia->execute([$nombreCurso]);

      $curso = $sentencia->fetch(PDO::FETCH_OBJ);

      return $curso;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function editarCurso($id, $nombreCurso)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        UPDATE curso SET nombre = ? 
        WHERE id = ?
        RETURNING *
      ');
      $sentencia->execute([$nombreCurso, $id]);
      $curso = $sentencia->fetch(PDO::FETCH_OBJ);
      return $curso;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function delete($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        DELETE FROM curso WHERE id = ?
      ');
      $sentencia->execute([$id]);

      return true;
    } catch (\Throwable $th) {
      return false;
    }
  }
}