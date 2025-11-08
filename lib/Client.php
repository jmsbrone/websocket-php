<?php

/**
 * Copyright (C) 2014-2022 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

use Override;
use Deprecated;
use ErrorException;
use InvalidArgumentException;
use Phrity\Net\Uri;
use Phrity\Util\ErrorHandler;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stringable;
use WebSocket\Message\Factory;
use WebSocket\Message\Message;

final class Client implements LoggerAwareInterface, Stringable
{
    use LoggerAwareTrait; // provides setLogger(LoggerInterface $logger)
    use OpcodeTrait;

    // Default options
    protected static $default_options = [
        'context' => null,
        'filter' => ['text', 'binary'],
        'fragment_size' => 4096,
        'headers' => null,
        'logger' => null,
        'origin' => null, // @deprecated
        'persistent' => false,
        'return_obj' => false,
        'timeout' => 5,
    ];

    private UriInterface $socket_uri;

    private ?Connection $connection = null;

    private array $options = [];

    private bool $listen = false;

    private ?string $last_opcode = null;

    // ---------- Magic methods ------------------------------------------------------

    /**
     * @param string|UriInterface $uri     A ws/wss-URI
     * @param array               $options
     *                                     Associative array containing:
     *                                     - context:       Set the stream context. Default: empty context
     *                                     - timeout:       Set the socket timeout in seconds.  Default: 5
     *                                     - fragment_size: Set framgemnt size.  Default: 4096
     *                                     - headers:       Associative array of headers to set/override.
     */
    public function __construct(string|UriInterface $uri, array $options = [])
    {
        $this->socket_uri = $this->parseUri($uri);
        $this->options = array_merge(self::$default_options, [
            'logger' => new NullLogger(),
        ], $options);
        $this->setLogger($this->options['logger']);
    }

    /**
     * Get string representation of instance.
     *
     * @return string string representation
     */
    #[Override]
    public function __toString(): string
    {
        return sprintf(
            '%s(%s)',
            static::class,
            $this->getName() ?: 'closed'
        );
    }

    // ---------- Client option functions --------------------------------------------

    /**
     * Set timeout.
     *
     * @param int $timeout timeout in seconds
     */
    public function setTimeout(int $timeout): void
    {
        $this->options['timeout'] = $timeout;
        if (!$this->isConnected()) {
            return;
        }

        $this->connection->setTimeout($timeout);
        $this->connection->setOptions($this->options);
    }

    /**
     * Set fragmentation size.
     *
     * @param int $fragment_size fragment size in bytes
     */
    public function setFragmentSize(int $fragment_size): self
    {
        $this->options['fragment_size'] = $fragment_size;
        $this->connection->setOptions($this->options);

        return $this;
    }

    /**
     * Get fragmentation size.
     *
     * @return int $fragment_size fragment size in bytes
     */
    public function getFragmentSize(): int
    {
        return $this->options['fragment_size'];
    }

    // ---------- Connection operations ----------------------------------------------

    /**
     * Send text message.
     *
     * @param string $payload content as string
     */
    public function text(string $payload): void
    {
        $this->send($payload);
    }

    /**
     * Send binary message.
     *
     * @param string $payload content as binary string
     */
    public function binary(string $payload): void
    {
        $this->send($payload, 'binary');
    }

    /**
     * Send ping.
     *
     * @param string $payload optional text as string
     */
    public function ping(string $payload = ''): void
    {
        $this->send($payload, 'ping');
    }

    /**
     * Send unsolicited pong.
     *
     * @param string $payload optional text as string
     */
    public function pong(string $payload = ''): void
    {
        $this->send($payload, 'pong');
    }

    /**
     * Send message.
     *
     * @param string $payload message to send
     * @param string $opcode  opcode to use, default: 'text'
     * @param bool   $masked  if message should be masked default: true
     */
    public function send(string $payload, string $opcode = 'text', bool $masked = true): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        if (!in_array($opcode, array_keys(self::$opcodes))) {
            $warning = sprintf("Bad opcode '%s'.  Try 'text' or 'binary'.", $opcode);
            $this->logger->warning($warning);

            throw new BadOpcodeException($warning);
        }

        $factory = new Factory();
        $message = $factory->create($opcode, $payload);
        $this->connection->pushMessage($message, $masked);
    }

    /**
     * Tell the socket to close.
     *
     * @param int    $status  http://tools.ietf.org/html/rfc6455#section-7.4
     * @param string $message a closing message, max 125 bytes
     */
    public function close(int $status = 1000, string $message = 'ttfn'): void
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->connection->close($status, $message);
    }

    /**
     * Disconnect from server.
     */
    public function disconnect(): void
    {
        if ($this->isConnected()) {
            $this->connection->disconnect();
        }
    }

    /**
     * Receive message.
     * Note that this operation will block reading.
     *
     * @return mixed message, text or null depending on settings
     */
    public function receive(): Message|string|null
    {
        $filter = $this->options['filter'];
        $return_obj = $this->options['return_obj'];

        if (!$this->isConnected()) {
            $this->connect();
        }

        while (true) {
            $message = $this->connection->pullMessage();
            $opcode = $message->getOpcode();
            if (in_array($opcode, $filter)) {
                $this->last_opcode = $opcode;
                $return = $return_obj ? $message : $message->getContent();

                break;
            }

            if ('close' === $opcode) {
                $this->last_opcode = null;
                $return = $return_obj ? $message : null;

                break;
            }
        }

        return $return;
    }

    // ---------- Connection functions -----------------------------------------------

    /**
     * Get last received opcode.
     *
     * @return null|string opcode
     */
    public function getLastOpcode(): ?string
    {
        return $this->last_opcode;
    }

    /**
     * Get close status on connection.
     *
     * @return null|int close status
     */
    public function getCloseStatus(): ?int
    {
        return $this->connection instanceof Connection ? $this->connection->getCloseStatus() : null;
    }

    /**
     * If Client has active connection.
     *
     * @return bool true if active connection
     */
    public function isConnected(): bool
    {
        return $this->connection && $this->connection->isConnected();
    }

    /**
     * Get name of local socket, or null if not connected.
     */
    public function getName(): false|string|null
    {
        return $this->isConnected() ? $this->connection->getName() : null;
    }

    /**
     * Get name of remote socket, or null if not connected.
     */
    public function getRemoteName(): false|string|null
    {
        return $this->isConnected() ? $this->connection->getRemoteName() : null;
    }

    /**
     * Get name of remote socket, or null if not connected.
     */
    #[Deprecated(message: 'will be removed in future version, use getPeer() instead')]
    public function getPier(): false|string|null
    {
        trigger_error(
            'getPier() is deprecated and will be removed in future version. Use getRemoteName() instead.',
            E_USER_DEPRECATED
        );

        return $this->getRemoteName();
    }

    // ---------- Helper functions ---------------------------------------------------

    /**
     * Perform WebSocket handshake.
     */
    protected function connect(): void
    {
        $this->connection = null;

        $host_uri = $this->socket_uri
            ->withScheme('wss' == $this->socket_uri->getScheme() ? 'ssl' : 'tcp')
            ->withPort($this->socket_uri->getPort() ?? ('wss' == $this->socket_uri->getScheme() ? 443 : 80))
            ->withPath('')
            ->withQuery('')
            ->withFragment('')
            ->withUserInfo('')
        ;

        // Path must be absolute
        $http_path = $this->socket_uri->getPath();
        if ('' === $http_path || '/' !== $http_path[0]) {
            $http_path = '/'.$http_path;
        }

        $http_uri = new Uri()
            ->withPath($http_path)
            ->withQuery($this->socket_uri->getQuery())
        ;

        // Set the stream context options if they're already set in the config
        if (isset($this->options['context'])) {
            // Suppress the error since we'll catch it below
            if ('stream-context' === @get_resource_type($this->options['context'])) {
                $context = $this->options['context'];
            } else {
                $error = "Stream context in \$options['context'] isn't a valid context.";
                $this->logger->error($error);

                throw new InvalidArgumentException($error);
            }
        } else {
            $context = stream_context_create();
        }

        $persistent = true === $this->options['persistent'];
        $flags = STREAM_CLIENT_CONNECT;
        $flags = $persistent ? $flags | STREAM_CLIENT_PERSISTENT : $flags;

        try {
            $handler = new ErrorHandler();
            $socket = $handler->with(function () use ($host_uri, $flags, $context) {
                $errno = null;
                $errstr = null;

                // Open the socket.
                return stream_socket_client(
                    $host_uri,
                    $errno,
                    $errstr,
                    $this->options['timeout'],
                    $flags,
                    $context
                );
            });
            if (!$socket) {
                throw new ErrorException('No socket');
            }
        } catch (ErrorException $errorException) {
            $error = sprintf('Could not open socket to "%s": %s (%d).', $host_uri->getAuthority(), $errorException->getMessage(), $errorException->getCode());
            $this->logger->error($error, ['severity' => $errorException->getSeverity()]);

            throw new ConnectionException($error, 0, [], $errorException);
        }

        $this->connection = new Connection($socket, $this->options);
        $this->connection->setLogger($this->logger);
        if (!$this->isConnected()) {
            $error = sprintf('Invalid stream on "%s".', $host_uri->getAuthority());
            $this->logger->error($error);

            throw new ConnectionException($error);
        }

        if (!$persistent || 0 === $this->connection->tell()) {
            // Set timeout on the stream as well.
            $this->connection->setTimeout($this->options['timeout']);

            // Generate the WebSocket key.
            $key = self::generateKey();

            // Default headers
            $headers = [
                'Host' => $host_uri->getAuthority(),
                'User-Agent' => 'websocket-client-php',
                'Connection' => 'Upgrade',
                'Upgrade' => 'websocket',
                'Sec-WebSocket-Key' => $key,
                'Sec-WebSocket-Version' => '13',
            ];

            // Handle basic authentication.
            if ($userinfo = $this->socket_uri->getUserInfo()) {
                $headers['authorization'] = 'Basic '.base64_encode($userinfo);
            }

            // Deprecated way of adding origin (use headers instead).
            if (isset($this->options['origin'])) {
                $headers['origin'] = $this->options['origin'];
            }

            // Add and override with headers from options.
            if (isset($this->options['headers'])) {
                $headers = array_merge($headers, $this->options['headers']);
            }

            $header = "GET {$http_uri} HTTP/1.1\r\n".implode(
                "\r\n",
                array_map(
                    fn (string $key, $value): string => sprintf('%s: %s', $key, $value),
                    array_keys($headers),
                    $headers
                )
            )."\r\n\r\n";

            // Send headers.
            $this->connection->write($header);

            // Get server response header (terminated with double CR+LF).
            $response = '';

            try {
                do {
                    $buffer = $this->connection->gets(1024);
                    $response .= $buffer;
                } while (0 === substr_count($response, "\r\n\r\n"));
            } catch (Exception $e) {
                throw new ConnectionException('Client handshake error', $e->getCode(), $e->getData(), $e);
            }

            // Validate response.
            if (!preg_match('#Sec-WebSocket-Accept:\s(.*)$#mUi', $response, $matches)) {
                $error = sprintf(
                    "Connection to '%s' failed: Server sent invalid upgrade response: %s",
                    (string) $this->socket_uri,
                    $response
                );
                $this->logger->error($error);

                throw new ConnectionException($error);
            }

            $keyAccept = trim($matches[1]);
            $expectedResonse = base64_encode(
                pack('H*', sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11'))
            );

            if ($keyAccept !== $expectedResonse) {
                $error = 'Server sent bad upgrade response.';
                $this->logger->error($error);

                throw new ConnectionException($error);
            }
        }

        $this->logger->info('Client connected to '.$this->socket_uri);
    }

    /**
     * Generate a random string for WebSocket key.
     *
     * @return string Random string
     */
    protected static function generateKey(): string
    {
        $key = '';
        for ($i = 0; $i < 16; ++$i) {
            $key .= chr(random_int(33, 126));
        }

        return base64_encode($key);
    }

    protected function parseUri(string|UriInterface $uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
        } elseif (is_string($uri)) {
            try {
                $uri = new Uri($uri);
            } catch (InvalidArgumentException $e) {
                throw new BadUriException(sprintf("Invalid URI '%s' provided.", $uri), 0, $e);
            }
        } else {
            throw new BadUriException('Provided URI must be a UriInterface or string.');
        }

        if (!in_array($uri->getScheme(), ['ws', 'wss'])) {
            throw new BadUriException("Invalid URI scheme, must be 'ws' or 'wss'.");
        }

        return $uri;
    }
}
