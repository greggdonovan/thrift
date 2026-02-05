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
 * @package thrift
 */

namespace Thrift\Exception;

use Thrift\Protocol\TProtocol;
use Thrift\Type\TType;

class TApplicationException extends TException
{
    /**
     * @var array<int, array{var: string, type: int}>
     */
    public static array $_TSPEC = [
        1 => ['var' => 'message', 'type' => TType::STRING],
        2 => ['var' => 'code', 'type' => TType::I32],
    ];

    public const int UNKNOWN = 0;
    public const int UNKNOWN_METHOD = 1;
    public const int INVALID_MESSAGE_TYPE = 2;
    public const int WRONG_METHOD_NAME = 3;
    public const int BAD_SEQUENCE_ID = 4;
    public const int MISSING_RESULT = 5;
    public const int INTERNAL_ERROR = 6;
    public const int PROTOCOL_ERROR = 7;
    public const int INVALID_TRANSFORM = 8;
    public const int INVALID_PROTOCOL = 9;
    public const int UNSUPPORTED_CLIENT_TYPE = 10;

    public function __construct(?string $message = null, int $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function read(TProtocol $output): int
    {
        return $this->_read('TApplicationException', self::$_TSPEC, $output);
    }

    public function write(TProtocol $output): int
    {
        $xfer = 0;
        $xfer += $output->writeStructBegin('TApplicationException');
        if ($message = $this->getMessage()) {
            $xfer += $output->writeFieldBegin('message', TType::STRING, 1);
            $xfer += $output->writeString($message);
            $xfer += $output->writeFieldEnd();
        }
        if ($code = $this->getCode()) {
            $xfer += $output->writeFieldBegin('type', TType::I32, 2);
            $xfer += $output->writeI32($code);
            $xfer += $output->writeFieldEnd();
        }
        $xfer += $output->writeFieldStop();
        $xfer += $output->writeStructEnd();

        return $xfer;
    }
}
