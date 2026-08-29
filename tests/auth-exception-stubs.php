<?php

/*
 * Este paquete no depende de linkedcode/auth-middleware — DefaultExceptionMapper
 * reconoce sus excepciones por nombre (string), justamente para no acoplarse.
 *
 * Para testear ese reconocimiento se declaran acá las mismas clases con el FQCN
 * real. El guard evita redeclararlas si el host sí tiene auth-middleware
 * instalado, en cuyo caso ganan las verdaderas.
 */

namespace Linkedcode\Middleware\Auth\Exception;

if (!class_exists(AuthException::class, false)) {
    class AuthException extends \RuntimeException {}

    class UnauthorizedException extends AuthException
    {
        public function __construct(string $message = 'Unauthorized', int $code = 401, ?\Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }

    class ForbiddenException extends AuthException
    {
        public function __construct(string $message = 'Forbidden', int $code = 403, ?\Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }
}
