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
use Thrift\Transport\TTransport;
use Thrift\Type\TType;
use Thrift\Exception\TProtocolException;

/**
 * Protocol base class module.
 */
abstract class TProtocol
{
    /**
     * Underlying transport
     *
     * @var TTransport
     */
    protected TTransport $trans_;

    /**
     * @param TTransport $trans
     */
    protected function __construct(TTransport $trans)
    {
        $this->trans_ = $trans;
    }

    /**
     * Accessor for transport
     *
     * @return TTransport
     */
    public function getTransport(): TTransport
    {
        return $this->trans_;
    }

    /**
     * Writes the message header
     *
     * @param string $name Function name
     * @param int $type message type TMessageType::CALL or TMessageType::REPLY
     * @param int $seqid The sequence id of this message
     */
    abstract public function writeMessageBegin(string $name, int $type, int $seqid): int;

    /**
     * Close the message
     */
    abstract public function writeMessageEnd(): int;

    /**
     * Writes a struct header.
     *
     * @param string $name Struct name
     * @throws TException on write error
     * @return int How many bytes written
     */
    abstract public function writeStructBegin(string $name): int;

    /**
     * Close a struct.
     *
     * @throws TException on write error
     * @return int How many bytes written
     */
    abstract public function writeStructEnd(): int;

    /*
     * Starts a field.
     *
     * @param string     $name Field name
     * @param int        $type Field type
     * @param int        $fid  Field id
     * @throws TException on write error
     * @return int How many bytes written
     */
    abstract public function writeFieldBegin(string $fieldName, int $fieldType, int $fieldId): int;

    abstract public function writeFieldEnd(): int;

    abstract public function writeFieldStop(): int;

    abstract public function writeMapBegin(int $keyType, int $valType, int $size): int;

    abstract public function writeMapEnd(): int;

    abstract public function writeListBegin(int $elemType, int $size): int;

    abstract public function writeListEnd(): int;

    abstract public function writeSetBegin(int $elemType, int $size): int;

    abstract public function writeSetEnd(): int;

    abstract public function writeBool(bool $bool): int;

    abstract public function writeByte(int $byte): int;

    abstract public function writeI16(int $i16): int;

    abstract public function writeI32(int $i32): int;

    abstract public function writeI64(int $i64): int;

    abstract public function writeDouble(float $dub): int;

    abstract public function writeString(string $str): int;

    /**
     * Reads the message header
     *
     * @param string $name Function name
     * @param int $type message type TMessageType::CALL or TMessageType::REPLY
     * @parem int $seqid The sequence id of this message
     */
    abstract public function readMessageBegin(&$name, &$type, &$seqid): int;

    /**
     * Read the close of message
     */
    abstract public function readMessageEnd(): int;

    abstract public function readStructBegin(&$name): int;

    abstract public function readStructEnd(): int;

    abstract public function readFieldBegin(&$name, &$fieldType, &$fieldId): int;

    abstract public function readFieldEnd(): int;

    abstract public function readMapBegin(&$keyType, &$valType, &$size): int;

    abstract public function readMapEnd(): int;

    abstract public function readListBegin(&$elemType, &$size): int;

    abstract public function readListEnd(): int;

    abstract public function readSetBegin(&$elemType, &$size): int;

    abstract public function readSetEnd(): int;

    abstract public function readBool(&$bool): int;

    abstract public function readByte(&$byte): int;

    abstract public function readI16(&$i16): int;

    abstract public function readI32(&$i32): int;

    abstract public function readI64(&$i64): int;

    abstract public function readDouble(&$dub): int;

    abstract public function readString(&$str): int;

    /**
     * The skip function is a utility to parse over unrecognized date without
     * causing corruption.
     *
     * @param int $type What type is it (defined in TType::class)
     */
    public function skip(int $type): int
    {
        switch ($type) {
            case TType::BOOL:
                return $this->readBool($bool);
            case TType::BYTE:
                return $this->readByte($byte);
            case TType::I16:
                return $this->readI16($i16);
            case TType::I32:
                return $this->readI32($i32);
            case TType::I64:
                return $this->readI64($i64);
            case TType::DOUBLE:
                return $this->readDouble($dub);
            case TType::STRING:
                return $this->readString($str);
            case TType::STRUCT:
                $result = $this->readStructBegin($name);
                while (true) {
                    $result += $this->readFieldBegin($name, $ftype, $fid);
                    if ($ftype == TType::STOP) {
                        break;
                    }
                    $result += $this->skip($ftype);
                    $result += $this->readFieldEnd();
                }
                $result += $this->readStructEnd();

                return $result;

            case TType::MAP:
                $result = $this->readMapBegin($keyType, $valType, $size);
                for ($i = 0; $i < $size; $i++) {
                    $result += $this->skip($keyType);
                    $result += $this->skip($valType);
                }
                $result += $this->readMapEnd();

                return $result;

            case TType::SET:
                $result = $this->readSetBegin($elemType, $size);
                for ($i = 0; $i < $size; $i++) {
                    $result += $this->skip($elemType);
                }
                $result += $this->readSetEnd();

                return $result;

            case TType::LST:
                $result = $this->readListBegin($elemType, $size);
                for ($i = 0; $i < $size; $i++) {
                    $result += $this->skip($elemType);
                }
                $result += $this->readListEnd();

                return $result;

            default:
                throw new TProtocolException(
                    'Unknown field type: ' . $type,
                    TProtocolException::INVALID_DATA
                );
        }
    }

    /**
     * Utility for skipping binary data
     *
     * @param TTransport $itrans TTransport object
     * @param int $type Field type
     */
    public static function skipBinary(TTransport $itrans, int $type): int
    {
        switch ($type) {
            case TType::BOOL:
                return $itrans->readAll(1);
            case TType::BYTE:
                return $itrans->readAll(1);
            case TType::I16:
                return $itrans->readAll(2);
            case TType::I32:
                return $itrans->readAll(4);
            case TType::I64:
                return $itrans->readAll(8);
            case TType::DOUBLE:
                return $itrans->readAll(8);
            case TType::STRING:
                $len = unpack('N', $itrans->readAll(4));
                $len = $len[1];
                if ($len > 0x7fffffff) {
                    $len = 0 - (($len - 1) ^ 0xffffffff);
                }

                return 4 + $itrans->readAll($len);

            case TType::STRUCT:
                $result = 0;
                while (true) {
                    $ftype = 0;
                    $fid = 0;
                    $data = $itrans->readAll(1);
                    $arr = unpack('c', $data);
                    $ftype = $arr[1];
                    if ($ftype == TType::STOP) {
                        break;
                    }
                    // I16 field id
                    $result += $itrans->readAll(2);
                    $result += self::skipBinary($itrans, $ftype);
                }

                return $result;

            case TType::MAP:
                // Ktype
                $data = $itrans->readAll(1);
                $arr = unpack('c', $data);
                $ktype = $arr[1];
                // Vtype
                $data = $itrans->readAll(1);
                $arr = unpack('c', $data);
                $vtype = $arr[1];
                // Size
                $data = $itrans->readAll(4);
                $arr = unpack('N', $data);
                $size = $arr[1];
                if ($size > 0x7fffffff) {
                    $size = 0 - (($size - 1) ^ 0xffffffff);
                }
                $result = 6;
                for ($i = 0; $i < $size; $i++) {
                    $result += self::skipBinary($itrans, $ktype);
                    $result += self::skipBinary($itrans, $vtype);
                }

                return $result;

            case TType::SET:
            case TType::LST:
                // Vtype
                $data = $itrans->readAll(1);
                $arr = unpack('c', $data);
                $vtype = $arr[1];
                // Size
                $data = $itrans->readAll(4);
                $arr = unpack('N', $data);
                $size = $arr[1];
                if ($size > 0x7fffffff) {
                    $size = 0 - (($size - 1) ^ 0xffffffff);
                }
                $result = 5;
                for ($i = 0; $i < $size; $i++) {
                    $result += self::skipBinary($itrans, $vtype);
                }

                return $result;

            default:
                throw new TProtocolException(
                    'Unknown field type: ' . $type,
                    TProtocolException::INVALID_DATA
                );
        }
    }
}
