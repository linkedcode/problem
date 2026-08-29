# linkedcode/problem-middleware

Middleware y utilidades para responder errores HTTP con formato [RFC 9457 / Problem Details](https://datatracker.ietf.org/doc/html/rfc9457) (antes RFC 7807) en aplicaciones PHP.

## Requisitos

- PHP `^8.2`

## Instalación

```bash
composer require linkedcode/problem-middleware
```

## Uso

El paquete provee el middleware, los contratos y un `DefaultExceptionMapper` que ya cubre los casos comunes. Cada aplicación aporta solo lo propio, extendiéndolo o implementando `ExceptionMapperInterface` desde cero.

```php
use Linkedcode\Middleware\Problem\Middleware\ProblemDetailsMiddleware;
use Linkedcode\Middleware\Problem\ProblemResponseFactory;

$middleware = new ProblemDetailsMiddleware(
    new MiExceptionMapper(),
    new ProblemResponseFactory($responseFactory),
);
```

### `DefaultExceptionMapper`

Cubre cuatro familias, en orden:

1. Las `ProblemException` de este paquete, que ya traen su status.
2. Las excepciones de `auth-middleware`: `UnauthorizedException` → 401,
   `ForbiddenException` → 403.
3. Las interfaces de excepción de `linkedcode/ddd`: `ValidationException` → 422
   (con los errores por campo en `errors`), `NotFoundException` → 404,
   `ForbiddenException` → 403, `ConflictException` → 409.
4. `InvalidArgumentException` → 422 y cualquier otra cosa → 500.

Los grupos 2 y 3 se reconocen por nombre, así que si no tenés esos paquetes
instalados simplemente nunca hacen match: este middleware no depende de ellos.

Lo habitual es extenderlo y delegar el resto en `parent`:

```php
use Linkedcode\Middleware\Problem\Mapper\DefaultExceptionMapper;

final class MiExceptionMapper extends DefaultExceptionMapper
{
    public function map(Throwable $e): ProblemInterface
    {
        // Solo lo propio de esta app; el resto ya lo cubre el padre.
        if ($e instanceof PaymentGatewayTimeout) {
            return new Problem('about:blank', 'Bad Gateway', 502, 'El proveedor de pagos no respondió.');
        }

        return parent::map($e);
    }
}
```

### Implementar el mapper desde cero

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

Para excepciones que no son de dominio puro (HTTP handlers, integraciones) se pueden usar las excepciones del paquete que ya conocen su status HTTP:

- `NotFoundException` → `404`
- `ForbiddenException` → `403`
- `ResourceConflictException` → `409`
- `HttpException` → status configurable
- `ValidationException` → `422`

Todas extienden `ProblemException` y se convierten a `Problem` vía `toProblem()`.

```php
use Linkedcode\Middleware\Problem\Exception\NotFoundException;
use Linkedcode\Middleware\Problem\Exception\HttpException;

throw new NotFoundException('Usuario no encontrado');
throw new HttpException(429, 'Too Many Requests');
```

Para que el mapper las procese, verificar `instanceof ProblemException` antes de los casos de dominio propios (ver ejemplo arriba).

## Excepciones de dominio

Las excepciones de dominio puro no deben extender las clases de este paquete ni conocer conceptos HTTP. Deben vivir en la capa de dominio de la aplicación y el mapper es quien hace el puente.

Si el proyecto usa `linkedcode/ddd`, las excepciones de dominio extienden las interfaces de ese paquete (`NotFoundException`, `ForbiddenException`, etc.) y el mapper las traduce a `Problem`.

## Integración con Slim 4

Registrar `ProblemDetailsMiddleware` en la pila de la app (no usar `addErrorMiddleware`/`setDefaultErrorHandler` de Slim, este middleware ya cubre todo el `Throwable` y hace el logueo):

```php
use Linkedcode\Middleware\Problem\Middleware\ProblemDetailsMiddleware;
use Linkedcode\Middleware\Problem\ProblemResponseFactory;

$app->add(new ProblemDetailsMiddleware(
    $mapper,
    new ProblemResponseFactory($responseFactory), // PSR-17 envuelto
    $logger,                                      // recomendado: loguea los errores
    debug: filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
));
```

## Errores 5xx: el detail se descarta

Un mapper se escribe naturalmente así:

```php
return new Problem('about:blank', 'Internal Server Error', 500, $e->getMessage());
```

…y eso filtra el mensaje crudo de la excepción al cliente. Un `PDOException`
expone el nombre de la base, la tabla y el SQL; otras exponen rutas del
filesystem.

Por eso el middleware **descarta `detail` y las extensions en toda respuesta 5xx**
y responde un mensaje genérico. Los 4xx quedan intactos: ahí el `detail` es un
mensaje deliberado para quien llama (`"email is not valid"`), no un accidente.

La excepción completa siempre llega al logger, así que no se pierde diagnóstico.
Para desarrollo local, `debug: true` devuelve el `detail` real en el body — nunca
lo actives en producción.

```json
// 500 en producción
{"type":"about:blank","title":"Internal Server Error","status":500,
 "detail":"An unexpected error occurred."}
```

## Negociación de contenido

El serializador sale del header `Accept`, respetando los q-values y los comodines
(`application/*`, `*/*`). Se reconocen `application/problem+json`,
`application/json`, `application/problem+xml`, `application/xml` y `text/xml`;
si nada es aceptable, responde JSON.

## Respuesta de ejemplo

```json
{
  "type": "https://example.com/problems/not-found",
  "title": "Not Found",
  "status": 404,
  "detail": "Order with id 'abc-123' not found"
}
```

---

## Reglas y Convenciones (desde la Arquitectura Global)

> [!WARNING]
> **Confusión de Nombres:**
> Las clases de excepciones de este paquete (ej. `NotFoundException`) comparten nombre con las **interfaces** de `linkedcode/ddd`, pero no son lo mismo. Las de este paquete son clases concretas de infraestructura HTTP; las de `linkedcode/ddd` son interfaces de dominio.

### ✅ Hacer (Dos)
* **Desde el dominio:** Implementar las interfaces de `linkedcode/ddd` en las excepciones de negocio y dejar que el `ExceptionMapperInterface` de tu proyecto las traduzca a respuestas Problem Details.
* **Desde la infraestructura (Actions, middlewares, adaptadores):** Usar las clases de este paquete directamente cuando el error sea puramente de transporte/HTTP (ej. API Key inválida) y no represente una regla de negocio.
* Dejar que este middleware centralice la serialización y traducción de errores.
* Pasar el `LoggerInterface` de la aplicación como tercer argumento del constructor del middleware para que se registren los errores no controlados.

### ❌ No Hacer (Donts)
* **No acoplar el dominio:** No importes las clases de excepciones de este paquete desde tus carpetas de `Domain/`. El dominio debe permanecer agnóstico de que corre detrás de una API HTTP.
* **No serializar a mano:** No escribas bloques `try/catch` manuales en tus Actions para estructurar respuestas de error en JSON si este middleware ya está configurado en tu stack.
