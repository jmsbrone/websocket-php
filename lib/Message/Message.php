<?php

/**
 * Copyright (C) 2014-2022 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket\Message;

use Override;
use DateTime;
use Stringable;

abstract class Message implements Stringable
{
    protected $opcode;

    protected DateTime $timestamp;

    public function __construct(protected string $payload = '')
    {
        $this->timestamp = new DateTime();
    }

    #[Override]
    public function __toString(): string
    {
        return static::class;
    }

    public function getOpcode(): string
    {
        return $this->opcode;
    }

    public function getLength(): int
    {
        return strlen($this->payload);
    }

    public function getTimestamp(): DateTime
    {
        return $this->timestamp;
    }

    public function getContent(): string
    {
        return $this->payload;
    }

    public function setContent(string $payload = ''): void
    {
        $this->payload = $payload;
    }

    public function hasContent(): bool
    {
        return '' !== $this->payload;
    }

    // Split messages into frames
    public function getFrames(bool $masked = true, int $framesize = 4096): array
    {
        $frames = [];
        $split = str_split($this->getContent(), $framesize) ?: [''];
        foreach ($split as $payload) {
            $frames[] = [false, $payload, 'continuation', $masked];
        }

        $frames[0][2] = $this->opcode;
        $frames[array_key_last($frames)][0] = true;

        return $frames;
    }
}
