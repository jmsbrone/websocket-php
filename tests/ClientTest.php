<?php

/**
 * Test case for Client.
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
class ClientTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        error_reporting(-1);
    }
}
