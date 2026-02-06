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

use Thrift\Exception\TTransportException;
use Thrift\Factory\TStringFuncFactory;

/**
 * HTTP client for Thrift
 *
 * @package thrift.transport
 */
class TCurlClient extends TTransport
{
    /**
     * Shared cURL handle
     */
    private static ?\CurlHandle $curlHandle = null;

    /**
     * The host to connect to
     */
    protected string $host_;

    /**
     * The port to connect on
     */
    protected int $port_;

    /**
     * The URI to request
     */
    protected string $uri_;

    /**
     * The scheme to use for the request, i.e. http, https
     */
    protected string $scheme_;

    /**
     * Buffer for the HTTP request data
     */
    protected string $request_;

    /**
     * Buffer for the HTTP response data.
     */
    protected ?string $response_ = null;

    /**
     * Read timeout
     */
    protected ?float $timeout_ = null;

    /**
     * Connection timeout
     */
    protected ?float $connectionTimeout_ = null;

    /**
     * http headers
     *
     * @var array<string, string>
     */
    protected array $headers_;

    /**
     * Make a new HTTP client.
     *
     * @param string $host
     * @param int $port
     * @param string $uri
     * @param string $scheme
     */
    public function __construct(
        string $host,
        int $port = 80,
        string $uri = '',
        string $scheme = 'http'
    ) {
        if ((TStringFuncFactory::create()->strlen($uri) > 0) && ($uri[0] != '/')) {
            $uri = '/' . $uri;
        }
        $this->scheme_ = $scheme;
        $this->host_ = $host;
        $this->port_ = $port;
        $this->uri_ = $uri;
        $this->request_ = '';
        $this->response_ = null;
        $this->timeout_ = null;
        $this->connectionTimeout_ = null;
        $this->headers_ = [];
    }

    /**
     * Set read timeout
     */
    public function setTimeoutSecs(float $timeout): void
    {
        $this->timeout_ = $timeout;
    }

    /**
     * Set connection timeout
     */
    public function setConnectionTimeoutSecs(float $connectionTimeout): void
    {
        $this->connectionTimeout_ = $connectionTimeout;
    }

    /**
     * Whether this transport is open.
     *
     * @return bool true if open
     */
    public function isOpen(): bool
    {
        return true;
    }

    /**
     * Open the transport for reading/writing
     *
     * @throws TTransportException if cannot open
     */
    public function open(): void
    {
    }

    /**
     * Close the transport.
     */
    public function close(): void
    {
        $this->request_ = '';
        $this->response_ = null;
    }

    /**
     * Read some data into the array.
     *
     * @param int $len How much to read
     * @return string The data that has been read
     * @throws TTransportException if cannot read any more data
     */
    public function read(int $len): string
    {
        if ($this->response_ === null || $len >= strlen($this->response_)) {
            return $this->response_ ?? '';
        } else {
            $ret = substr($this->response_, 0, $len);
            $this->response_ = substr($this->response_, $len);

            return $ret;
        }
    }

    /**
     * Guarantees that the full amount of data is read. Since TCurlClient gets entire payload at
     * once, parent readAll cannot be used.
     *
     * @return string The data, of exact length
     * @throws TTransportException if cannot read data
     */
    public function readAll(int $len): string
    {
        $data = $this->read($len);

        if (TStringFuncFactory::create()->strlen($data) !== $len) {
            throw new TTransportException('TCurlClient could not read '.$len.' bytes');
        }

        return $data;
    }

    /**
     * Writes some data into the pending buffer
     *
     * @param string $buf The data to write
     * @throws TTransportException if writing fails
     */
    public function write(string $buf): void
    {
        $this->request_ .= $buf;
    }

    /**
     * Opens and sends the actual request over the HTTP connection
     *
     * @throws TTransportException if a writing error occurs
     */
    public function flush(): void
    {
        if (!self::$curlHandle) {
            register_shutdown_function([self::class, 'closeCurlHandle']);
            self::$curlHandle = curl_init();
            curl_setopt(self::$curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(self::$curlHandle, CURLOPT_USERAGENT, 'PHP/TCurlClient');
            curl_setopt(self::$curlHandle, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt(self::$curlHandle, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt(self::$curlHandle, CURLOPT_MAXREDIRS, 1);
        }
        // God, PHP really has some esoteric ways of doing simple things.
        $host = $this->host_ . ($this->port_ != 80 ? ':' . $this->port_ : '');
        $fullUrl = $this->scheme_ . "://" . $host . $this->uri_;

        $headers = [];
        $defaultHeaders = [
            'Accept' => 'application/x-thrift',
            'Content-Type' => 'application/x-thrift',
            'Content-Length' => TStringFuncFactory::create()->strlen($this->request_)
        ];
        foreach (array_merge($defaultHeaders, $this->headers_) as $key => $value) {
            $headers[] = "$key: $value";
        }

        curl_setopt(self::$curlHandle, CURLOPT_HTTPHEADER, $headers);

        if ($this->timeout_ > 0) {
            if ($this->timeout_ < 1.0) {
                // Timestamps smaller than 1 second are ignored when CURLOPT_TIMEOUT is used
                curl_setopt(self::$curlHandle, CURLOPT_TIMEOUT_MS, (int)(1000 * $this->timeout_));
            } else {
                curl_setopt(self::$curlHandle, CURLOPT_TIMEOUT, (int)$this->timeout_);
            }
        }
        if ($this->connectionTimeout_ > 0) {
            if ($this->connectionTimeout_ < 1.0) {
                // Timestamps smaller than 1 second are ignored when CURLOPT_CONNECTTIMEOUT is used
                curl_setopt(self::$curlHandle, CURLOPT_CONNECTTIMEOUT_MS, (int)(1000 * $this->connectionTimeout_));
            } else {
                curl_setopt(self::$curlHandle, CURLOPT_CONNECTTIMEOUT, (int)$this->connectionTimeout_);
            }
        }
        curl_setopt(self::$curlHandle, CURLOPT_POSTFIELDS, $this->request_);
        $this->request_ = '';

        curl_setopt(self::$curlHandle, CURLOPT_URL, $fullUrl);
        $response = curl_exec(self::$curlHandle);
        $this->response_ = is_string($response) ? $response : null;
        $responseError = curl_error(self::$curlHandle);

        $code = curl_getinfo(self::$curlHandle, CURLINFO_HTTP_CODE);

        // Handle non 200 status code / connect failure
        if ($this->response_ === null || $code !== 200) {
            curl_close(self::$curlHandle);
            self::$curlHandle = null;
            $this->response_ = null;
            $error = 'TCurlClient: Could not connect to ' . $fullUrl;
            if ($responseError) {
                $error .= ', ' . $responseError;
            }
            if ($code) {
                $error .= ', HTTP status code: ' . $code;
            }
            throw new TTransportException($error, TTransportException::UNKNOWN);
        }
    }

    public static function closeCurlHandle(): void
    {
        try {
            if (self::$curlHandle) {
                curl_close(self::$curlHandle); #This function has no effect. Prior to PHP 8.0.0, this function was used to close the resource.
                self::$curlHandle = null;
            }
        } catch (\Exception $x) {
            #it's not possible to throw an exception by calling a function that has no effect
            error_log('There was an error closing the curl handle: ' . $x->getMessage());
        }
    }

    /**
     * Add headers to the HTTP request
     *
     * @param array<string, string> $headers
     */
    public function addHeaders(array $headers): void
    {
        $this->headers_ = array_merge($this->headers_, $headers);
    }
}
