<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Tests\Mapper;

use Linkedcode\DDD\Domain\Exception\ConflictException;
use Linkedcode\DDD\Domain\Exception\ForbiddenException;
use Linkedcode\DDD\Domain\Exception\NotFoundException;
use Linkedcode\DDD\Domain\Exception\ValidationException;

/**
 * Fábricas de excepciones que implementan los contratos del kernel, para probar
 * que DefaultExceptionMapper los reconoce sin depender de linkedcode/ddd.
 *
 * Las interfaces se declaran en tests/kernel-exception-stubs.php.
 */
final class KernelExceptions
{
    public static function notFound(string $message = 'no encontrado'): \RuntimeException
    {
        return new class ($message) extends \RuntimeException implements NotFoundException {};
    }

    public static function forbidden(string $message = 'prohibido'): \RuntimeException
    {
        return new class ($message) extends \RuntimeException implements ForbiddenException {};
    }

    public static function conflict(string $message = 'ya existe'): \RuntimeException
    {
        return new class ($message) extends \RuntimeException implements ConflictException {};
    }

    /** @param array<string, string> $errors */
    public static function validation(array $errors): \RuntimeException
    {
        return new class ('datos inválidos', $errors) extends \RuntimeException implements ValidationException {
            /** @param array<string, string> $errors */
            public function __construct(string $message, private readonly array $errors)
            {
                parent::__construct($message);
            }

            /** @return array<string, mixed> */
            public function errors(): array
            {
                return $this->errors;
            }
        };
    }
}
