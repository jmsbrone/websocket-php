<?php

/**
 * Test case for Exceptions.
 */

declare(strict_types=1);

namespace WebSocket;

use PHPUnit\Framework\TestCase;
use Throwable;

class ExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        error_reporting(-1);
    }

    public function testConnectionException(): void
    {
        try {
            throw new ConnectionException(
                'An error message',
                ConnectionException::EOF,
                ['test' => 'with data'],
                new TimeoutException(
                    'Nested exception',
                    ConnectionException::TIMED_OUT
                )
            );
        } catch (Throwable $throwable) {
        }

        $this->assertInstanceOf(ConnectionException::class, $throwable);
        $this->assertInstanceOf(Exception::class, $throwable);
        $this->assertInstanceOf('Exception', $throwable);
        $this->assertInstanceOf('Throwable', $throwable);
        $this->assertEquals('An error message', $throwable->getMessage());
        $this->assertEquals(1025, $throwable->getCode());
        $this->assertEquals(['test' => 'with data'], $throwable->getData());

        $p = $throwable->getPrevious();
        $this->assertInstanceOf(TimeoutException::class, $p);
        $this->assertInstanceOf(ConnectionException::class, $p);
        $this->assertEquals('Nested exception', $p->getMessage());
        $this->assertEquals(1024, $p->getCode());
        $this->assertEquals([], $p->getData());
    }
}
