<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Session;
use App\Models\Area;
use Tests\Support\DbCase;

/**
 * Database::transaction() (D1) — transacción cooperativa:
 *  - sin transacción externa: begin + commit, o rollback si el callable lanza;
 *  - con transacción externa (nested, p.ej. DbCase::usarComo): NO emite
 *    begin/commit y el rollback queda a cargo del caller externo.
 */
final class DatabaseTransactionTest extends DbCase
{
    public function testTransactionCommitsCuandoNoHayTransaccionExterna(): void
    {
        Session::set('user_id', self::$adminId);
        Database::reset();

        $creada = Database::transaction(static fn (): array => Area::create('P4T Txn Commit'));

        self::assertNotEmpty($creada['id']);
        self::assertNotNull(Area::findById($creada['id']), 'El INSERT commiteado es visible fuera de la transacción.');
    }

    public function testTransactionHaceRollbackCuandoElCallableLanza(): void
    {
        Session::set('user_id', self::$adminId);
        Database::reset();

        try {
            Database::transaction(static function (): void {
                Area::create('P4T Txn Rollback');
                throw new \RuntimeException('boom');
            });
            self::fail('El callable debe propagar la excepción.');
        } catch (\RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertSame(
            [],
            Area::searchByNombre('P4T Txn Rollback'),
            'El INSERT de la transacción fallida no persiste.'
        );
    }

    public function testTransactionAnidadaEsCooperativa(): void
    {
        $this->usarComo(self::$adminId); // abre la transacción externa (DbCase)

        $creada = Database::transaction(static fn (): array => Area::create('P4T Txn Anidada'));

        self::assertNotEmpty($creada['id']);
        self::assertTrue(
            Database::getConnection()->inTransaction(),
            'La transacción interna no debe commitear la externa.'
        );
        self::assertNotNull(Area::findById($creada['id']), 'La fila es visible dentro de la transacción externa.');

        // La externa conserva el control: rollback manual elimina lo anidado.
        Database::getConnection()->rollBack();

        self::assertSame(
            [],
            Area::searchByNombre('P4T Txn Anidada'),
            'El rollback de la transacción externa revierte lo insertado por la anidada.'
        );
    }

    public function testTripleAnidacionNoRompeElRollbackExterno(): void
    {
        $this->usarComo(self::$adminId);

        Database::transaction(static function (): void {
            Area::create('P4T Txn Nivel1');
            Database::transaction(static function (): void {
                Area::create('P4T Txn Nivel2');
                Database::transaction(static function (): void {
                    Area::create('P4T Txn Nivel3');
                });
            });
        });

        self::assertTrue(Database::getConnection()->inTransaction(), 'Toda la cadena sigue en la transacción externa.');

        Database::getConnection()->rollBack();

        self::assertSame(
            [],
            Area::searchByNombre('P4T Txn Nivel'),
            'El rollback externo revierte los tres niveles anidados.'
        );
    }
}