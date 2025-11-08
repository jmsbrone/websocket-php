<?php

/**
 * Test case for Server.
 * Not final e that this test is performed by mocking socket/stream calls.
 */

declare(strict_types=1);

namespace WebSocket;

use Override;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ServerTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        error_reporting(-1);
    }
}
