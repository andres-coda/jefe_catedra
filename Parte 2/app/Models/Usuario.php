<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

/**
 * Usuario de la plataforma (tabla usuario).
 * La contraseña se guarda como hash bcrypt (password_hash / password_verify).
 */
final class Usuario
{
    private const TABLE = 'usuario';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(string $id): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre, email, pass, cargo, rol, creado_en FROM ' . self::TABLE . ' WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre, email, pass, cargo, rol, creado_en FROM ' . self::TABLE
            . ' WHERE lower(email) = lower(?)'
        );
        $statement->execute([$email]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Crea un usuario y devuelve la fila insertada.
     *
     * @throws \InvalidArgumentException si el email no es válido
     * @throws \RuntimeException si el email ya está registrado (message 'email_existente')
     *
     * @return array<string, mixed>
     */
    public static function create(string $nombre, string $email, string $password): array
    {
        if (!self::validarEmail($email)) {
            throw new \InvalidArgumentException('Email no válido.');
        }

        try {
            Database::getConnection()
                ->prepare('INSERT INTO ' . self::TABLE . ' (nombre, email, pass) VALUES (?, ?, ?)')
                ->execute([$nombre, $email, self::hashPassword($password)]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') { // unique_violation (uq_usuario_email)
                throw new \RuntimeException('email_existente');
            }
            throw $exception;
        }

        $row = self::findByEmail($email);
        if ($row === null) {
            throw new \RuntimeException('No se pudo recuperar el usuario creado.');
        }

        return $row;
    }

    /**
     * Actualiza nombre/email y, opcionalmente, la contraseña (pass = NULL la deja igual).
     *
     * @throws \RuntimeException si el email ya pertenece a otro usuario ('email_existente')
     */
    public static function updateProfile(string $id, string $nombre, string $email, ?string $nuevoPassHash = null): void
    {
        $sets = ['nombre = ?', 'email = ?'];
        $params = [$nombre, $email];

        if ($nuevoPassHash !== null) {
            $sets[] = 'pass = ?';
            $params[] = $nuevoPassHash;
        }

        $params[] = $id;

        try {
            Database::getConnection()
                ->prepare('UPDATE ' . self::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE id = ?')
                ->execute($params);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') { // unique_violation (uq_usuario_email)
                throw new \RuntimeException('email_existente');
            }
            throw $exception;
        }
    }

    /**
     * True si el email puede usarse (no lo posee otro usuario distinto de $exceptoId).
     */
    public static function emailDisponible(string $email, string $exceptoId): bool
    {
        $existente = self::findByEmail($email);

        return $existente === null || $existente['id'] === $exceptoId;
    }

    public static function validarEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}