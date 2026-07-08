# linkedcode/problem-middleware

Middleware y utilidades para responder errores HTTP con formato
[RFC 7807 / Problem Details](https://datatracker.ietf.org/doc/html/rfc7807)
en aplicaciones PHP.

## Requisitos

- PHP `^8.1`

## Instalacion

```bash
composer require linkedcode/problem-middleware
```

## Uso

El paquete provee el middleware y los contratos. Cada aplicacion implementa
su propio `ExceptionMapperInterface` para traducir sus excepciones a `Problem`.

```php
use Linkedcode\Middleware\Problem\Middleware\ProblemDetailsMiddleware;
use Linkedcode\Middleware\Problem\ProblemResponseFactory;

$middleware = new ProblemDetailsMiddleware(
    new MiExceptionMapper(),
    new ProblemResponseFactory($responseFactory),
);
```

### Implementar el mapper

```php
use Throwable;
use Linkedcode\Middleware\Problem\Problem;
use Linkedcode\Middleware\Problem\ProblemInterface;
use Linkedcode\Middleware\Problem\Mapper\ExceptionMapperInterface;
use Linkedcode\Middleware\Problem\Exception\ProblemException;

final class MiExceptionMapper implements ExceptionMapperInterface
{
    public function map(Throwable $e): ProblemInterface
    {
        if ($e instanceof ProblemException) {
            return $e->toProblem();
        }

        // mapear excepciones de dominio propias
        if ($e instanceof MiNotFoundException) {
            return new Problem(
                type: 'https://example.com/problems/not-found',
                title: 'Not Found',
                status: 404,
                detail: $e->getMessage(),
            );
        }

        return new Problem(
            type: 'about:blank',
            title: 'Internal Server Error',
            status: 500,
        );
    }
}
```

## Excepciones de infraestructura incluidas

Para excepciones que no son de dominio puro (HTTP handlers, integraciones) se
pueden usar las excepciones del paquete que ya conocen su status HTTP:

- `NotFoundException` → `404`
- `ForbiddenException` → `403`
- `ResourceConflictException` → `409`
- `HttpException` → status configurable

Todas extienden `ProblemException` y se convierten a `Problem` via `toProblem()`.

```php
use Linkedcode\Middleware\Problem\Exception\NotFoundException;
use Linkedcode\Middleware\Problem\Exception\HttpException;

throw new NotFoundException('Usuario no encontrado');
throw new HttpException(429, 'Too Many Requests');
```

Para que el mapper las procese, verificar `instanceof ProblemException` antes
de los casos de dominio propios (ver ejemplo arriba).

## Excepciones de dominio

Las excepciones de dominio puro no deben extender las clases de este paquete
ni conocer conceptos HTTP. Deben vivir en la capa de dominio de la aplicacion
y el mapper es quien hace el puente.

Si el proyecto usa `linkedcode/ddd`, las excepciones de dominio extienden las
clases base de ese paquete (`NotFoundException`, `ForbiddenException`, etc.) y
el mapper las traduce a `Problem`.

## Integracion con Slim 4

```php
use Linkedcode\Middleware\Problem\Integration\SlimErrorHandler;

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(
    new SlimErrorHandler($mapper, $responseFactory)
);
```

## Respuesta de ejemplo

```json
{
  "type": "https://example.com/problems/not-found",
  "title": "Not Found",
  "status": 404,
  "detail": "Order with id 'abc-123' not found"
}
```
