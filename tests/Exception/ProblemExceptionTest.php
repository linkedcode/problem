<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Linkedcode\Middleware\Problem\Exception\NotFoundException;
use Linkedcode\Middleware\Problem\Exception\ForbiddenException;
use Linkedcode\Middleware\Problem\Exception\ResourceConflictException;
use Linkedcode\Middleware\Problem\Exception\HttpException;
final class ProblemExceptionTest extends TestCase
{
    public function testNotFoundException(): void
    {
        $e = new NotFoundException('Item not found');
        $problem = $e->toProblem();

        $this->assertSame(404, $problem->getStatus());
        $this->assertSame('Resource Not Found', $problem->getTitle());
        $this->assertSame('Item not found', $problem->getDetail());
    }

    public function testForbiddenException(): void
    {
        $e = new ForbiddenException('Access denied');
        $problem = $e->toProblem();

        $this->assertSame(403, $problem->getStatus());
        $this->assertSame('Forbidden', $problem->getTitle());
    }

    public function testResourceConflictException(): void
    {
        $e = new ResourceConflictException('Already exists');
        $problem = $e->toProblem();

        $this->assertSame(409, $problem->getStatus());
        $this->assertSame('Conflict', $problem->getTitle());
    }

    public function testHttpException(): void
    {
        $e = new HttpException(422, 'Unprocessable');
        $problem = $e->toProblem();

        $this->assertSame(422, $problem->getStatus());
        $this->assertSame('Unprocessable', $problem->getTitle());
    }

    public function testHttpExceptionDefaultTitle(): void
    {
        $e = new HttpException(500);
        $problem = $e->toProblem();

        $this->assertSame('HTTP Error', $problem->getTitle());
    }

}
