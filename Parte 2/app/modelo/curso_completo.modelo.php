<?php
require_once('app/modelos/Modelo.php');

class CursoCompletoModelo extends Modelo
{

  public function obtenerCursosCompletos()
  {
    try {

      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM vw_curso 
          ORDER BY escula, curso
        ');
      $sentencia->execute();
      $cursoCompletos = $sentencia->fetchAll(PDO::FETCH_OBJ);

      return $cursoCompletos;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerCursoCompletoPorId($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM vw_cursoCompleto_completo 
          WHERE id_cursoCompleto_completo = ?
        ');
      $sentencia->execute([$id]);
      $cursoCompleto = $sentencia->fetch(PDO::FETCH_OBJ);
      return $cursoCompleto;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function insertarCursoCompleto($planificacion, $diagnostico, $tpd1, $tpd2, $lnck_draiver, $id_curso_escuela)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO curso_completo (planificacion, diagnostico, tpd1, tpd2, lnck_draiver, id_curso_escuela) VALUES (?,?,?,?,?,?)
          RETURNING *
        ');
      $sentencia->execute([$planificacion, $diagnostico, $tpd1, $tpd2, $lnck_draiver, $id_curso_escuela]);

      $cursoCompleto = $sentencia->fetch(PDO::FETCH_OBJ);

      return $cursoCompleto;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function editarCursoCompleto($id, $planificacion, $diagnostico, $tpd1, $tpd2, $lnck_draiver)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        UPDATE curso_completo SET 
          planificacion = ?, 
          diagnostico = ?, 
          tpd1 = ?, 
          tpd2 = ?, 
          lnck_draiver = ?, 
        WHERE id = ?
        RETURNING *
      ');
      $sentencia->execute([$planificacion, $diagnostico, $tpd1, $tpd2, $lnck_draiver, $id]);
      $cursoCompleto = $sentencia->fetch(PDO::FETCH_OBJ);
      return $cursoCompleto;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function delete($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        DELETE FROM curso_completo WHERE id = ?
      ');
      $sentencia->execute([$id]);

      return true;
    } catch (\Throwable $th) {
      return false;
    }
  }
}