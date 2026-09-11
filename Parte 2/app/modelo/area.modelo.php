<?php
require_once('app/modelo/modelo.php');

class AreaModelo extends Modelo
{

  public function obtenerAreasPorIdEscuela($idEscuela)
  {
    try {

      $sentencia = $this->getPdo()->prepare('
          SELECT DISTINCT ON (a.nombre)
            ce.id_materia,
            ce.id_escuela,
            ce.id,
            a.nombre AS area,
            a.id AS id_area
          FROM curso_escuela ce
          JOIN materia m ON m.id = ce.id_materia
          JOIN area a ON a.id = m.id_area
          WHERE ce.id_escuela = ?
          ORDER BY a.nombre
        ');
      $sentencia->execute([$idEscuela]);
      $areas = $sentencia->fetchAll(PDO::FETCH_OBJ);

      return $areas;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerAreaPorNombre($nombre)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
         SELECT * FROM area
         WHERE nombre = ?
        ');
      $sentencia->execute([$nombre]);

      $area = $sentencia->fetch(PDO::FETCH_OBJ);

      return $area;
    } catch (\Throwable $th) {
      die($th->getMessage());
    }
  }


  public function obtenerMateriasPorNombreArea($nombre)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
      SELECT a.nombre AS area, a.id, m.nombre AS materia, m.id AS id_materia, m.id_area FROM area a
      RIGHT JOIN materia m ON m.id_area = a.id
      WHERE a.nombre = ?
      ');
      $sentencia->execute([$nombre]);      
      $area = $sentencia->fetchAll(PDO::FETCH_OBJ);      
      return $area;
    } catch (\Throwable $th) {
      die($th->getMessage());
    }
  }

  public function obtenerAreaPorId($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM area a
          JOIN materia m ON m.id_area = a.id
          WHERE a.id = ?
        ');
      $sentencia->execute([$id]);
      $area = $sentencia->fetch(PDO::FETCH_OBJ);
      return $area;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function insertarArea($nombreArea)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO area (nombre) 
          VALUES (?)
          RETURNING *
        ');
      $sentencia->execute([$nombreArea]);

      $area = $sentencia->fetch(PDO::FETCH_OBJ);

      return $area;
    } catch (\Throwable $th) {
      die($th->getMessage());
    }
  }

  public function editarArea($id, $nombreArea)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        UPDATE area SET nombre = ? 
        WHERE id = ?
        RETURNING *
      ');
      $sentencia->execute([$nombreArea, $id]);
      $area = $sentencia->fetch(PDO::FETCH_OBJ);
      return $area;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function delete($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        DELETE FROM area WHERE id = ?
      ');
      $sentencia->execute([$id]);

      return true;
    } catch (\Throwable $th) {
      return false;
    }
  }
}