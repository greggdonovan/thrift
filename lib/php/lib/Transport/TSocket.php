<?php
/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements. See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership. The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @package thrift.transport
 */

namespace Thrift\Transport;

use Thrift\Exception\TException;
use Thrift\Exception\TTransportException;
use Thrift\Factory\TStringFuncFactory;

/**
 * Sockets implementation of the TTransport interface.
 *
 * @package thrift.transport
 */
class TSocket extends TTransport
{
    /**
     * Handle to PHP socket
     *
     * @var resource|null
     */
    protected mixed $handle_ = null;

    /**
     * Remote hostname
     */
    protected string $host_ = 'localhost';

    /**
     * Remote port
     */
    protected int $port_ = 9090;

    /**
     * Send timeout in seconds.
     *
     * Combined with sendTimeoutUsec this is used for send timeouts.
     */
    protected int $sendTimeoutSec_ = 0;

    /**
     * Send timeout in microseconds.
     *
     * Combined with sendTimeoutSec this is used for send timeouts.
     */
    protected int $sendTimeoutUsec_ = 100000;

    /**
     * Recv timeout in seconds
     *
     * Combined with recvTimeoutUsec this is used for recv timeouts.
     */
    protected int $recvTimeoutSec_ = 0;

    /**
     * Recv timeout in microseconds
     *
     * Combined with recvTimeoutSec this is used for recv timeouts.
     */
    protected int $recvTimeoutUsec_ = 750000;

    /**
     * Persistent socket or plain?
     */
    protected bool $persist_ = false;

    /**
     * Debugging on?
     */
    protected bool $debug_ = false;

    /**
     * Debug handler
     */
    protected ?callable $debugHandler_ = null;

    /**
     * Socket constructor
     *
     * @param string $host Remote hostname
     * @param int $port Remote port
     * @param bool $persist Whether to use a persistent socket
     * @param callable|null $debugHandler Function to call for error logging
     */
    public function __construct(
        string $host = 'localhost',
        int $port = 9090,
        bool $persist = false,
        ?callable $debugHandler = null
    ) {
        $this->host_ = $host;
        $this->port_ = $port;
        $this->persist_ = $persist;
        $this->debugHandler_ = $debugHandler ?? 'error_log';
    }

    /**
     * @param resource $handle
     */
    public function setHandle(mixed $handle): void
    {
        $this->handle_ = $handle;
        stream_set_blocking($this->handle_, false);
    }

    /**
     * Sets the send timeout.
     *
     * @param int $timeout Timeout in milliseconds.
     */
    public function setSendTimeout(int $timeout): void
    {
        $this->sendTimeoutSec_ = (int) floor($timeout / 1000);
        $this->sendTimeoutUsec_ =
            ($timeout - ($this->sendTimeoutSec_ * 1000)) * 1000;
    }

    /**
     * Sets the receive timeout.
     *
     * @param int $timeout Timeout in milliseconds.
     */
    public function setRecvTimeout(int $timeout): void
    {
        $this->recvTimeoutSec_ = (int) floor($timeout / 1000);
        $this->recvTimeoutUsec_ =
            ($timeout - ($this->recvTimeoutSec_ * 1000)) * 1000;
    }

    /**
     * Sets debugging output on or off
     */
    public function setDebug(bool $debug): void
    {
        $this->debug_ = $debug;
    }

    /**
     * Get the host that this socket is connected to
     */
    public function getHost(): string
    {
        return $this->host_;
    }

    /**
     * Get the remote port that this socket is connected to
     */
    public function getPort(): int
    {
        return $this->port_;
    }

    /**
     * Tests whether this is open
     *
     * @return bool true if the socket is open
     */
    public function isOpen(): bool
    {
        return is_resource($this->handle_);
    }

    /**
     * Connects the socket.
     */
    public function open(): void
    {
        if ($this->isOpen()) {
            throw new TTransportException('Socket already connected', TTransportException::ALREADY_OPEN);
        }

        if (empty($this->host_)) {
            throw new TTransportException('Cannot open null host', TTransportException::NOT_OPEN);
        }

        if ($this->port_ <= 0 && strpos($this->host_, 'unix://') !== 0) {
            throw new TTransportException('Cannot open without port', TTransportException::NOT_OPEN);
        }

        if ($this->persist_) {
            $this->handle_ = @pfsockopen(
                $this->host_,
                $this->port_,
                $errno,
                $errstr,
                $this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000)
            );
        } else {
            $this->handle_ = @fsockopen(
                $this->host_,
                $this->port_,
                $errno,
                $errstr,
                $this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000)
            );
        }

        // Connect failed?
        if ($this->handle_ === false) {
            $error = 'TSocket: Could not connect to ' .
                $this->host_ . ':' . $this->port_ . ' (' . $errstr . ' [' . $errno . '])';
            if ($this->debug_) {
                call_user_func($this->debugHandler_, $error);
            }
            throw new TException($error);
        }

        if (function_exists('socket_import_stream') && function_exists('socket_set_option')) {
            // warnings silenced due to bug https://bugs.php.net/bug.php?id=70939
            $socket = socket_import_stream($this->handle_);
            if ($socket !== false) {
                @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
            }
        }
    }

    /**
     * Closes the socket.
     */
    public function close(): void
    {
        @fclose($this->handle_);
        $this->handle_ = null;
    }

    /**
     * Read from the socket at most $len bytes.
     *
     * This method will not wait for all the requested data, it will return as
     * soon as any data is received.
     *
     * @param int $len Maximum number of bytes to read.
     * @return string Binary data
     */
    public function read(int $len): string
    {
        $null = null;
        $read = array($this->handle_);
        $readable = @stream_select(
            $read,
            $null,
            $null,
            $this->recvTimeoutSec_,
            $this->recvTimeoutUsec_
        );

        if ($readable > 0) {
            $data = fread($this->handle_, $len);
            if ($data === false) {
                throw new TTransportException('TSocket: Could not read ' . $len . ' bytes from ' .
                    $this->host_ . ':' . $this->port_);
            } elseif ($data == '' && feof($this->handle_)) {
                throw new TTransportException('TSocket read 0 bytes');
            }

            return $data;
        } elseif ($readable === 0) {
            throw new TTransportException('TSocket: timed out reading ' . $len . ' bytes from ' .
                $this->host_ . ':' . $this->port_);
        } else {
            throw new TTransportException('TSocket: Could not read ' . $len . ' bytes from ' .
                $this->host_ . ':' . $this->port_);
        }
    }

    /**
     * Write to the socket.
     *
     * @param string $buf The data to write
     */
    public function write(string $buf): void
    {
        $null = null;
        $write = array($this->handle_);

        // keep writing until all the data has been written
        while (TStringFuncFactory::create()->strlen($buf) > 0) {
            // wait for stream to become available for writing
            $writable = @stream_select(
                $null,
                $write,
                $null,
                $this->sendTimeoutSec_,
                $this->sendTimeoutUsec_
            );
            if ($writable > 0) {
                // write buffer to stream
                $written = fwrite($this->handle_, $buf);
                $closed_socket = $written === 0 && feof($this->handle_);
                if ($written === -1 || $written === false || $closed_socket) {
                    throw new TTransportException(
                        'TSocket: Could not write ' . TStringFuncFactory::create()->strlen($buf) . ' bytes ' .
                        $this->host_ . ':' . $this->port_
                    );
                }
                // determine how much of the buffer is left to write
                $buf = TStringFuncFactory::create()->substr($buf, $written);
            } elseif ($writable === 0) {
                throw new TTransportException(
                    'TSocket: timed out writing ' . TStringFuncFactory::create()->strlen($buf) . ' bytes from ' .
                    $this->host_ . ':' . $this->port_
                );
            } else {
                throw new TTransportException(
                    'TSocket: Could not write ' . TStringFuncFactory::create()->strlen($buf) . ' bytes ' .
                    $this->host_ . ':' . $this->port_
                );
            }
        }
    }

    /**
     * Flush output to the socket.
     *
     * Since read(), readAll() and write() operate on the sockets directly,
     * this is a no-op
     *
     * If you wish to have flushable buffering behaviour, wrap this TSocket
     * in a TBufferedTransport.
     */
    public function flush(): void
    {
        // no-op
    }
}
