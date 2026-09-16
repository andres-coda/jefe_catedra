<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\DriveLink;
use App\Models\Usuario;
use PDOException;
use Tests\Support\DbCase;

/**
 * Integración con PostgreSQL: autenticación bcrypt (findForAuth case-insensitive,
 * hash $2y$, verify) y validación de URL de Drive en app + CHECK de la base.
 */
final class IntegrationTest extends DbCase
{
    public function testBcryptHashYVerify(): void
    {
        $hash = Usuario::hashPassword('p4t-clave');

        self::assertStringStartsWith('$2y$', $hash, 'PASSWORD_DEFAULT en PHP 8 → bcrypt $2y$.');
        self::assertTrue(Usuario::verifyPassword('p4t-clave', $hash));
        self::assertFalse(Usuario::verifyPassword('clave-incorrecta', $hash));
    }

    public function testFindForAuthCaseInsensitiveYVerify(): void
    {
        $this->anonimo();

        $emailMezclado = 'P4T.JEFE@P4T.TEST';
        $row = Usuario::findForAuth($emailMezclado);

        self::assertNotNull($row, 'fc_autenticar es case-insensitive en el email.');
        self::assertSame(self::$jefeId, $row['id']);
        self::assertTrue(Usuario::verifyPassword('p4t-clave', $row['pass']));
    }

    public function testFindForAuthEmailInexistenteDevuelveNull(): void
    {
        $this->anonimo();

        self::assertNull(Usuario::findForAuth('nadie@p4t.test'));
    }

    public function testEsUrlValida(): void
    {
        self::assertTrue(DriveLink::esUrlValida('https://drive.google.com/file/d/abc123'));
        self::assertTrue(DriveLink::esUrlValida('http://ejemplo.com/recursos'));

        self::assertFalse(DriveLink::esUrlValida('ftp://drive.google.com/x'));
        self::assertFalse(DriveLink::esUrlValida('javascript:alert(1)'));
        self::assertFalse(DriveLink::esUrlValida('no-es-una-url'));
        self::assertFalse(DriveLink::esUrlValida(''));
        self::assertFalse(DriveLink::esUrlValida('https://' . str_repeat('a', 2041) . '.com'), 'Longitud > 2048 → inválida.');
    }

    public function testUrlInvalidaRechazadaPorLaBase(): void
    {
        $this->usarComo(self::$jefeId);

        try {
            DriveLink::create(self::$cursoEscuelaId, 'ftp://x', 'P4T inválido');
            self::fail('La base debe rechazar una URL que no empieza con http(s):// (ch_drive_link_url).');
        } catch (PDOException $exception) {
            self::assertSame('23514', $exception->getCode(), 'CHECK violation = SQLSTATE 23514.');
        }
    }

    public function testUrlValidaPersistidaPorLaBase(): void
    {
        $this->usarComo(self::$jefeId);

        $creado = DriveLink::create(self::$cursoEscuelaId, 'https://drive.google.com/integracion', 'P4T integración');

        self::assertNotEmpty($creado['id']);
        self::assertSame('https://drive.google.com/integracion', $creado['url']);
    }
}