<?php

/**
 * Test case for Message subsec final tion.
 */

declare(strict_types=1);

namespace WebSocket;

use WebSocket\Message\Binary;
use WebSocket\Message\Ping;
use WebSocket\Message\Pong;
use WebSocket\Message\Close;
use Override;
use PHPUnit\Framework\TestCase;
use WebSocket\Message\Factory;
use WebSocket\Message\Message;
use WebSocket\Message\Text;

/**
 * @internal
 *
 * @coversNothing
 */
class MessageTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        error_reporting(-1);
    }

    public function testFactory(): void
    {
        $factory = new Factory();
        $message = $factory->create('text', 'Some content');
        $this->assertInstanceOf(Text::class, $message);
        $message = $factory->create('binary', 'Some content');
        $this->assertInstanceOf(Binary::class, $message);
        $message = $factory->create('ping', 'Some content');
        $this->assertInstanceOf(Ping::class, $message);
        $message = $factory->create('pong', 'Some content');
        $this->assertInstanceOf(Pong::class, $message);
        $message = $factory->create('close', 'Some content');
        $this->assertInstanceOf(Close::class, $message);
    }

    public function testMessage(): void
    {
        $message = new Text('Some content');
        $this->assertInstanceOf(Message::class, $message);
        $this->assertInstanceOf(Text::class, $message);
        $this->assertEquals('Some content', $message->getContent());
        $this->assertEquals('text', $message->getOpcode());
        $this->assertEquals(12, $message->getLength());
        $this->assertTrue($message->hasContent());
        $this->assertInstanceOf('DateTime', $message->getTimestamp());
        $message->setContent('');
        $this->assertEquals(0, $message->getLength());
        $this->assertFalse($message->hasContent());
        $this->assertEquals(Text::class, $message);
    }

    public function testBadOpcode(): void
    {
        $factory = new Factory();
        $this->expectException(BadOpcodeException::class);
        $this->expectExceptionMessage("Invalid opcode 'invalid' provided");
        $factory->create('invalid', 'Some content');
    }
}
