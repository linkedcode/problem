<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Tests\Mapper;

use Linkedcode\Middleware\Auth\Exception\ForbiddenException;
use Linkedcode\Middleware\Auth\Exception\UnauthorizedException;

/**
 * Fábricas de las excepciones de auth-middleware, para probar que
 * DefaultExceptionMapper las reconoce sin depender de ese paquete.
 *
 * Las clases se declaran en tests/auth-exception-stubs.php.
 */
final class AuthExceptions
{
    public static function unauthorized(string $message = 'Unauthorized'): UnauthorizedException
    {
        return new UnauthorizedException($message);
    }

    public static function forbidden(string $message = 'Forbidden'): ForbiddenException
    {
        return new ForbiddenException($message);
    }
}
