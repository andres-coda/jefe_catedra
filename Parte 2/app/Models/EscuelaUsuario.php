<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Relación usuario-escuela (tabla escuela_usuario): rol del usuario DENTRO de
 * cada escuela. La jerarquía de permisos la resuelve PermissionMiddleware;
 * aquí solo se persiste y lee el pivot.
 */
final class EscuelaUsuario
{
    /** Roles permitidos (CHECK ch_escuela_usuario_rol del esquema). */
    public const ROLES = ['directivo', 'jefe', 'user'];

    /**
     * Rol del usuario en la escuela, o null si no tiene pivot.
     */
    public static function getRol(string $escuelaId, string $usuarioId): ?string
    {
        $statement = Database::getConnection()->prepare(
            'SELECT rol FROM escuela_usuario WHERE id_escuela = ? AND id_usuario = ?'
        );
        $statement->execute([$escuelaId, $usuarioId]);
        $rol = $statement->fetchColumn();

        return $rol !== false ? (string) $rol : null;
    }

    /**
     * Asigna (o actualiza) el rol de un usuario en una escuela buscándolo por
     * email. Devuelve null si el email no corresponde a un usuario registrado.
     *
     * @throws \InvalidArgumentException si el rol no está en ROLES
     *
     * @return array{usuario: array<string, mixed>, rol: string}|null
     */
    public static function assignByEmail(string $escuelaId, string $email, string $rol): ?array
    {
        if (!in_array($rol, self::ROLES, true)) {
            throw new \InvalidArgumentException(sprintf('Rol inválido: %s', $rol));
        }

        $usuario = Usuario::findByEmail($email);
        if ($usuario === null) {
            return null;
        }

        Database::getConnection()
            ->prepare(
                'INSERT INTO escuela_usuario (id_escuela, id_usuario, rol) VALUES (?, ?, ?)
                 ON CONFLICT (id_escuela, id_usuario) DO UPDATE SET rol = EXCLUDED.rol'
            )
            ->execute([$escuelaId, $usuario['id'], $rol]);

        return ['usuario' => $usuario, 'rol' => $rol];
    }

    /**
     * Usuarios (con su rol) de una escuela, ordenados por nombre.
     *
     * @return array<int, array{id: string, nombre: string, email: string, rol: string}>
     */
    public static function listByEscuela(string $escuelaId): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT u.id AS id, u.nombre AS nombre, u.email AS email, eu.rol AS rol
             FROM escuela_usuario eu
             JOIN usuario u ON u.id = eu.id_usuario
             WHERE eu.id_escuela = ?
             ORDER BY u.nombre'
        );
        $statement->execute([$escuelaId]);

        return $statement->fetchAll();
    }
}