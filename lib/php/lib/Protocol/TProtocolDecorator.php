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
 * @package thrift.protocol
 */

namespace Thrift\Protocol;

use Thrift\Exception\TException;

/**
 * <code>TProtocolDecorator</code> forwards all requests to an enclosed
 * <code>TProtocol</code> instance, providing a way to author concise
 * concrete decorator subclasses. While it has no abstract methods, it
 * is marked abstract as a reminder that by itself, it does not modify
 * the behaviour of the enclosed <code>TProtocol</code>.
 *
 * @package Thrift\Protocol
 */
abstract class TProtocolDecorator extends TProtocol
{
    /**
     * Instance of protocol, to which all operations will be forwarded.
     *
     * @var TProtocol
     */
    private $concreteProtocol_;

    /**
     * Constructor of <code>TProtocolDecorator</code> class.
     * Encloses the specified protocol.
     *
     * @param TProtocol $protocol All operations will be forward to this instance. Must be non-null.
     */
    protected function __construct(TProtocol $protocol)
    {
        parent::__construct($protocol->getTransport());
        $this->concreteProtocol_ = $protocol;
    }

    /**
     * Writes the message header.
     *
     * @param string $name  Function name
     * @param int    $type  message type TMessageType::CALL or TMessageType::REPLY
     * @param int    $seqid The sequence id of this message
     */
    public function writeMessageBegin(string $name, int $type, int $seqid): int
    {
        return $this->concreteProtocol_->writeMessageBegin($name, $type, $seqid);
    }

    /**
     * Closes the message.
     */
    public function writeMessageEnd(): int
    {
        return $this->concreteProtocol_->writeMessageEnd();
    }

    /**
     * Writes a struct header.
     *
     * @param string $name Struct name
     *
     * @throws TException on write error
     * @return int        How many bytes written
     */
    public function writeStructBegin(string $name): int
    {
        return $this->concreteProtocol_->writeStructBegin($name);
    }

    /**
     * Close a struct.
     *
     * @throws TException on write error
     * @return int        How many bytes written
     */
    public function writeStructEnd(): int
    {
        return $this->concreteProtocol_->writeStructEnd();
    }

    public function writeFieldBegin(string $fieldName, int $fieldType, int $fieldId): int
    {
        return $this->concreteProtocol_->writeFieldBegin($fieldName, $fieldType, $fieldId);
    }

    public function writeFieldEnd(): int
    {
        return $this->concreteProtocol_->writeFieldEnd();
    }

    public function writeFieldStop(): int
    {
        return $this->concreteProtocol_->writeFieldStop();
    }

    public function writeMapBegin(int $keyType, int $valType, int $size): int
    {
        return $this->concreteProtocol_->writeMapBegin($keyType, $valType, $size);
    }

    public function writeMapEnd(): int
    {
        return $this->concreteProtocol_->writeMapEnd();
    }

    public function writeListBegin(int $elemType, int $size): int
    {
        return $this->concreteProtocol_->writeListBegin($elemType, $size);
    }

    public function writeListEnd(): int
    {
        return $this->concreteProtocol_->writeListEnd();
    }

    public function writeSetBegin(int $elemType, int $size): int
    {
        return $this->concreteProtocol_->writeSetBegin($elemType, $size);
    }

    public function writeSetEnd(): int
    {
        return $this->concreteProtocol_->writeSetEnd();
    }

    public function writeBool(bool $bool): int
    {
        return $this->concreteProtocol_->writeBool($bool);
    }

    public function writeByte(int $byte): int
    {
        return $this->concreteProtocol_->writeByte($byte);
    }

    public function writeI16(int $i16): int
    {
        return $this->concreteProtocol_->writeI16($i16);
    }

    public function writeI32(int $i32): int
    {
        return $this->concreteProtocol_->writeI32($i32);
    }

    public function writeI64(int $i64): int
    {
        return $this->concreteProtocol_->writeI64($i64);
    }

    public function writeDouble(float $dub): int
    {
        return $this->concreteProtocol_->writeDouble($dub);
    }

    public function writeString(string $str): int
    {
        return $this->concreteProtocol_->writeString($str);
    }

    /**
     * Reads the message header
     *
     * @param string $name  Function name
     * @param int    $type  message type TMessageType::CALL or TMessageType::REPLY
     * @param int    $seqid The sequence id of this message
     */
    public function readMessageBegin(&$name, &$type, &$seqid): int
    {
        return $this->concreteProtocol_->readMessageBegin($name, $type, $seqid);
    }

    /**
     * Read the close of message
     */
    public function readMessageEnd(): int
    {
        return $this->concreteProtocol_->readMessageEnd();
    }

    public function readStructBegin(&$name): int
    {
        return $this->concreteProtocol_->readStructBegin($name);
    }

    public function readStructEnd(): int
    {
        return $this->concreteProtocol_->readStructEnd();
    }

    public function readFieldBegin(&$name, &$fieldType, &$fieldId): int
    {
        return $this->concreteProtocol_->readFieldBegin($name, $fieldType, $fieldId);
    }

    public function readFieldEnd(): int
    {
        return $this->concreteProtocol_->readFieldEnd();
    }

    public function readMapBegin(&$keyType, &$valType, &$size): int
    {
        return $this->concreteProtocol_->readMapBegin($keyType, $valType, $size);
    }

    public function readMapEnd(): int
    {
        return $this->concreteProtocol_->readMapEnd();
    }

    public function readListBegin(&$elemType, &$size): int
    {
        return $this->concreteProtocol_->readListBegin($elemType, $size);
    }

    public function readListEnd(): int
    {
        return $this->concreteProtocol_->readListEnd();
    }

    public function readSetBegin(&$elemType, &$size): int
    {
        return $this->concreteProtocol_->readSetBegin($elemType, $size);
    }

    public function readSetEnd(): int
    {
        return $this->concreteProtocol_->readSetEnd();
    }

    public function readBool(&$bool): int
    {
        return $this->concreteProtocol_->readBool($bool);
    }

    public function readByte(&$byte): int
    {
        return $this->concreteProtocol_->readByte($byte);
    }

    public function readI16(&$i16): int
    {
        return $this->concreteProtocol_->readI16($i16);
    }

    public function readI32(&$i32): int
    {
        return $this->concreteProtocol_->readI32($i32);
    }

    public function readI64(&$i64): int
    {
        return $this->concreteProtocol_->readI64($i64);
    }

    public function readDouble(&$dub): int
    {
        return $this->concreteProtocol_->readDouble($dub);
    }

    public function readString(&$str): int
    {
        return $this->concreteProtocol_->readString($str);
    }
}
