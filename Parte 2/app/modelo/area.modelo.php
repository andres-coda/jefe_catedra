<?php
require_once('app/modelo/modelo.php');

class AreaModelo extends Modelo
{

  public function obtenerAreas()
  {
    try {

      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM area ORDER BY nombre
        ');
      $sentencia->execute();
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
          INSERT INTO area (nombre) 
          VALUES (?)
          RETURNING *
        ');
      $sentencia->execute([$nombre]);

      $area = $sentencia->fetch(PDO::FETCH_OBJ);

      return $area;
    } catch (\Throwable $th) {
      return false;
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
         SELECT * FROM area WHERE nombre = ?
        ');
      $sentencia->execute([$nombreArea]);

      $area = $sentencia->fetch(PDO::FETCH_OBJ);

      return $area;
    } catch (\Throwable $th) {
      return false;
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