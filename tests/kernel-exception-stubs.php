<?php

/*
 * Este paquete no depende de linkedcode/ddd — DefaultExceptionMapper reconoce
 * sus interfaces por nombre (string), justamente para no acoplarse.
 *
 * Para testear ese reconocimiento se declaran acá las mismas interfaces con el
 * FQCN real del kernel. El guard evita redeclararlas si el host sí tiene
 * linkedcode/ddd instalado, en cuyo caso ganan las verdaderas.
 */

namespace Linkedcode\DDD\Domain\Exception;

if (!interface_exists(DomainException::class, false)) {
    interface DomainException extends \Throwable {}

    interface NotFoundException extends DomainException {}

    interface ForbiddenException extends DomainException {}

    interface ConflictException extends DomainException {}

    interface ValidationException extends DomainException
    {
        /** @return array<string, mixed> */
        public function errors(): array;
    }
}
