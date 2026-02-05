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
 */

namespace Thrift\Server;

use Thrift\Transport\TSSLSocket;
use Thrift\Transport\TTransport;

/**
 * Socket implementation of a server agent.
 *
 * @package thrift.transport
 */
class TSSLServerSocket extends TServerSocket
{
    /**
     * Stream context for SSL
     *
     * @var resource
     */
    protected mixed $context_;

    /**
     * ServerSocket constructor
     *
     * @param string $host Host to listen on
     * @param int $port Port to listen on
     * @param resource|null $context Stream context
     */
    public function __construct(string $host = 'localhost', int $port = 9090, mixed $context = null)
    {
        $ssl_host = $this->getSSLHost($host);
        parent::__construct($ssl_host, $port);
        // Initialize a stream context if not provided
        if ($context === null) {
            $context = stream_context_create();
        }
        $this->context_ = $context;
    }

    public function getSSLHost(string $host): string
    {
        $transport_protocol_loc = strpos($host, "://");
        if ($transport_protocol_loc === false) {
            $host = 'ssl://' . $host;
        }
        return $host;
    }

    /**
     * Opens a new socket server handle
     *
     * @return void
     */
    public function listen(): void
    {
        $this->listener_ = @stream_socket_server(
            $this->host_ . ':' . $this->port_,
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $this->context_
        );
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

        $socket = new TSSLSocket();
        $socket->setHandle($handle);

        return $socket;
    }
}
