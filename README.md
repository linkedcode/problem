# linkedcode/problem-middleware

Middleware y utilidades para responder errores HTTP con formato
[RFC 7807 / Problem Details](https://datatracker.ietf.org/doc/html/rfc7807)
en aplicaciones PHP (Slim 4).

## Requisitos

- PHP `^8.1`
- Slim `^4.0`

## Instalacion

```bash
composer require linkedcode/problem-middleware
```

## Uso con Slim 4

```php
<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Linkedcode\Middleware\Problem\Mapper\DefaultExceptionMapper;
use Linkedcode\Middleware\Problem\ProblemResponseFactory;
use Linkedcode\Middleware\Problem\Integration\SlimErrorHandler;

$app = AppFactory::create();

$responseFactory = new ProblemResponseFactory($app->getResponseFactory());
$mapper = new DefaultExceptionMapper();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(
    new SlimErrorHandler($mapper, $responseFactory, true)
);
```

## Excepciones incluidas

- `NotFoundException` -> status `404`
- `ValidationException` -> status `422` + extension para campos invalidos
- `HttpException` -> status configurable

Todas extienden `ProblemException` y se serializan automaticamente como
`application/problem+json`.

## Respuesta de ejemplo

```json
{
  "type": "about:blank",
  "title": "Validation Error",
  "status": 422,
  "detail": "Validation failed",
  "errors": [
    {
      "detail": "must be a positive integer",
      "pointer": "#/age"
    },
    {
      "detail": "must be 'green', 'red' or 'blue'",
      "pointer": "#/profile/color"
    }
  ]
}
```
