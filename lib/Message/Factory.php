<?php

/**
 * Copyright (C) 2014-2022 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket\Message;

use WebSocket\BadOpcodeException;

class Factory
{
    public function create(string $opcode, string $payload = ''): Message
    {
        return match ($opcode) {
            'text' => new Text($payload),
            'binary' => new Binary($payload),
            'ping' => new Ping($payload),
            'pong' => new Pong($payload),
            'close' => new Close($payload),
            default => throw new BadOpcodeException(sprintf("Invalid opcode '%s' provided", $opcode)),
        };
    }
}
