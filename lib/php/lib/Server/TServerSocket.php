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

namespace Thrift\Server;

use Thrift\Transport\TSocket;
use Thrift\Transport\TTransport;

/**
 * Socket implementation of a server agent.
 *
 * @package thrift.transport
 */
class TServerSocket extends TServerTransport
{
    /**
     * Handle for the listener socket
     *
     * @var resource|null
     */
    protected mixed $listener_ = null;

    /**
     * Port for the listener to listen on
     */
    protected int $port_;

    /**
     * Timeout when listening for a new client
     */
    protected int $acceptTimeout_ = 30000;

    /**
     * Host to listen on
     */
    protected string $host_;

    /**
     * ServerSocket constructor
     *
     * @param string $host Host to listen on
     * @param int $port Port to listen on
     */
    public function __construct(string $host = 'localhost', int $port = 9090)
    {
        $this->host_ = $host;
        $this->port_ = $port;
    }

    /**
     * Sets the accept timeout
     *
     * @param int $acceptTimeout
     * @return void
     */
    public function setAcceptTimeout(int $acceptTimeout): void
    {
        $this->acceptTimeout_ = $acceptTimeout;
    }

    /**
     * Opens a new socket server handle
     *
     * @return void
     */
    public function listen(): void
    {
        $this->listener_ = stream_socket_server('tcp://' . $this->host_ . ':' . $this->port_);
    }

    /**
     * Closes the socket server handle
     *
     * @return void
     */
    public function close(): void
    {
        @fclose($this->listener_);
        $this->listener_ = null;
    }

    /**
     * Implementation of accept. If not client is accepted in the given time
     *
     * @return TTransport|null
     */
    protected function acceptImpl(): ?TTransport
    {
        $handle = @stream_socket_accept($this->listener_, $this->acceptTimeout_ / 1000.0);
        if (!$handle) {
            return null;
        }

        $socket = new TSocket();
        $socket->setHandle($handle);

        return $socket;
    }
}
