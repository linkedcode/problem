<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Tests\Mapper;

use Linkedcode\Middleware\Problem\Exception\HttpException;
use Linkedcode\Middleware\Problem\Exception\NotFoundException as PackageNotFoundException;
use Linkedcode\Middleware\Problem\Mapper\DefaultExceptionMapper;
use Linkedcode\Middleware\Problem\Problem;
use Linkedcode\Middleware\Problem\ProblemInterface;
use PHPUnit\Framework\TestCase;
use Throwable;

final class DefaultExceptionMapperTest extends TestCase
{
    private DefaultExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DefaultExceptionMapper();
    }

    public function test_package_exceptions_keep_their_own_status(): void
    {
        self::assertSame(404, $this->mapper->map(new PackageNotFoundException('no está'))->getStatus());
        self::assertSame(418, $this->mapper->map(new HttpException(418, 'soy una tetera'))->getStatus());
    }

    public function test_maps_kernel_not_found_to_404(): void
    {
        $problem = $this->mapper->map(KernelExceptions::notFound('address no existe'));

        self::assertSame(404, $problem->getStatus());
        self::assertSame('Not Found', $problem->getTitle());
        self::assertSame('address no existe', $problem->getDetail());
    }

    public function test_maps_kernel_forbidden_to_403(): void
    {
        self::assertSame(403, $this->mapper->map(KernelExceptions::forbidden())->getStatus());
    }

    public function test_maps_kernel_conflict_to_409(): void
    {
        self::assertSame(409, $this->mapper->map(KernelExceptions::conflict())->getStatus());
    }

    public function test_maps_kernel_validation_to_422_with_field_errors(): void
    {
        $problem = $this->mapper->map(KernelExceptions::validation(['street' => 'no puede estar vacía']));

        self::assertSame(422, $problem->getStatus());
        self::assertSame(['errors' => ['street' => 'no puede estar vacía']], $problem->getExtensions());
    }

    public function test_validation_without_errors_adds_no_extensions(): void
    {
        self::assertSame([], $this->mapper->map(KernelExceptions::validation([]))->getExtensions());
    }

    public function test_maps_invalid_argument_to_422(): void
    {
        self::assertSame(422, $this->mapper->map(new \InvalidArgumentException('mal'))->getStatus());
    }

    public function test_falls_back_to_500(): void
    {
        self::assertSame(500, $this->mapper->map(new \RuntimeException('boom'))->getStatus());
    }

    /**
     * El host hereda para lo suyo y delega el resto: es el uso previsto de esta
     * clase, y lo que evita que cada app se olvide de las interfaces del kernel.
     */
    public function test_a_host_mapper_can_extend_and_delegate(): void
    {
        $mapper = new class extends DefaultExceptionMapper {
            public function map(Throwable $e): ProblemInterface
            {
                if ($e instanceof \DomainException) {
                    return new Problem('about:blank', 'Unauthorized', 401, $e->getMessage());
                }

                return parent::map($e);
            }
        };

        self::assertSame(401, $mapper->map(new \DomainException('sin token'))->getStatus());
        self::assertSame(404, $mapper->map(KernelExceptions::notFound())->getStatus());
        self::assertSame(500, $mapper->map(new \RuntimeException('boom'))->getStatus());
    }
}
