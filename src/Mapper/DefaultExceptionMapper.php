<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Mapper;

use Linkedcode\Middleware\Problem\Exception\ProblemException;
use Linkedcode\Middleware\Problem\Problem;
use Linkedcode\Middleware\Problem\ProblemInterface;
use Throwable;

/**
 * Mapper base: traduce a RFC 9457 todo lo que este paquete puede reconocer por
 * sí solo, y deja al host sólo lo que es propio de su aplicación.
 *
 * Cubre tres familias, en orden:
 *
 *  1. ProblemException — las excepciones de este paquete, que ya traen status.
 *  2. Las interfaces de excepción del kernel (linkedcode/ddd), reconocidas por
 *     nombre para no acoplar este middleware a ese paquete: quien no lo tenga
 *     instalado simplemente nunca hace match.
 *  3. InvalidArgumentException → 422, y cualquier otra cosa → 500.
 *
 * El host extiende esta clase y sobreescribe map() para sus propios casos
 * (401 de auth, 502 de un servicio externo), delegando el resto en parent.
 */
class DefaultExceptionMapper implements ExceptionMapperInterface
{
    /**
     * Interfaz del kernel => [status, title].
     *
     * Se listan por FQCN en string a propósito: `instanceof` con un nombre de
     * clase que no existe devuelve false en lugar de fallar, así que este
     * paquete no necesita depender de linkedcode/ddd.
     *
     * El orden importa: ValidationException va primero porque es la única que
     * aporta errores por campo.
     */
    private const DOMAIN_EXCEPTIONS = [
        'Linkedcode\DDD\Domain\Exception\ValidationException' => [422, 'Unprocessable Entity'],
        'Linkedcode\DDD\Domain\Exception\NotFoundException'   => [404, 'Not Found'],
        'Linkedcode\DDD\Domain\Exception\ForbiddenException'  => [403, 'Forbidden'],
        'Linkedcode\DDD\Domain\Exception\ConflictException'   => [409, 'Conflict'],
    ];

    public function map(Throwable $e): ProblemInterface
    {
        // Las excepciones de este paquete ya saben su status.
        if ($e instanceof ProblemException) {
            return $e->toProblem();
        }

        foreach (self::DOMAIN_EXCEPTIONS as $interface => [$status, $title]) {
            if (!$e instanceof $interface) {
                continue;
            }

            return new Problem(
                type: 'about:blank',
                title: $title,
                status: $status,
                detail: $e->getMessage(),
                extensions: $this->extensionsFor($e),
            );
        }

        if ($e instanceof \InvalidArgumentException) {
            return new Problem('about:blank', 'Unprocessable Entity', 422, $e->getMessage());
        }

        return new Problem('about:blank', 'Internal Server Error', 500, $e->getMessage());
    }

    /**
     * ValidationException del kernel expone errors(); el resto no aporta
     * extensiones. El shape de errors() lo decide cada implementación, así que
     * se reenvía tal cual.
     *
     * @return array<string, mixed>
     */
    private function extensionsFor(Throwable $e): array
    {
        if (!method_exists($e, 'errors')) {
            return [];
        }

        $errors = $e->errors();

        return $errors === [] ? [] : ['errors' => $errors];
    }
}
