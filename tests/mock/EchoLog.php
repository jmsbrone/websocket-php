<?php

/**
 * Simple echo logger (only available when running in dev environment).
 */

namespace WebSocket;

use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

class EchoLog implements LoggerInterface
{
    use LoggerTrait;

    #[Override]
    /**
     * @param mixed $level
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $message = $this->interpolate($message, $context);
        $context_string = [] === $context ? '' : json_encode($context);
        echo str_pad((string) $level, 8).sprintf(' | %s %s%s', $message, $context_string, PHP_EOL);
    }

    public function interpolate(string|Stringable $message, array $context = []): string
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            // Check that the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{'.$key.'}'] = $val;
            }
        }

        // Interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}
