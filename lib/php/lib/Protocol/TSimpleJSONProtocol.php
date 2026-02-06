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
use Thrift\Exception\TProtocolException;
use Thrift\Protocol\SimpleJSON\Context;
use Thrift\Protocol\SimpleJSON\ListContext;
use Thrift\Protocol\SimpleJSON\StructContext;
use Thrift\Protocol\SimpleJSON\MapContext;
use Thrift\Protocol\SimpleJSON\CollectionMapKeyException;

/**
 * SimpleJSON implementation of thrift protocol, ported from Java.
 */
class TSimpleJSONProtocol extends TProtocol
{
    const COMMA = ',';
    const COLON = ':';
    const LBRACE = '{';
    const RBRACE = '}';
    const LBRACKET = '[';
    const RBRACKET = ']';
    const QUOTE = '"';

    const NAME_MAP = "map";
    const NAME_LIST = "lst";
    const NAME_SET = "set";

    protected $writeContext_ = null;
    protected $writeContextStack_ = [];

    /**
     * Push a new write context onto the stack.
     */
    protected function pushWriteContext(Context $c)
    {
        $this->writeContextStack_[] = $this->writeContext_;
        $this->writeContext_ = $c;
    }

    /**
     * Pop the last write context off the stack
     */
    protected function popWriteContext()
    {
        $this->writeContext_ = array_pop($this->writeContextStack_);
    }

    /**
     * Used to make sure that we are not encountering a map whose keys are containers
     */
    protected function assertContextIsNotMapKey($invalidKeyType)
    {
        if ($this->writeContext_->isMapKey()) {
            throw new CollectionMapKeyException(
                "Cannot serialize a map with keys that are of type " .
                $invalidKeyType
            );
        }
    }

    private function writeJSONString($b)
    {
        $this->writeContext_->write();

        $this->trans_->write(json_encode((string)$b));
    }

    private function writeJSONInteger($num)
    {
        $isMapKey = $this->writeContext_->isMapKey();

        $this->writeContext_->write();

        if ($isMapKey) {
            $this->trans_->write(self::QUOTE);
        }

        $this->trans_->write((int)$num);

        if ($isMapKey) {
            $this->trans_->write(self::QUOTE);
        }
    }

    private function writeJSONDouble($num)
    {
        $isMapKey = $this->writeContext_->isMapKey();

        $this->writeContext_->write();

        if ($isMapKey) {
            $this->trans_->write(self::QUOTE);
        }

        #TODO add compatibility with NAN and INF
        $this->trans_->write(json_encode((float)$num));

        if ($isMapKey) {
            $this->trans_->write(self::QUOTE);
        }
    }

    /**
     * Constructor
     */
    public function __construct($trans)
    {
        parent::__construct($trans);
        $this->writeContext_ = new Context();
    }

    /**
     * Writes the message header
     *
     * @param string $name  Function name
     * @param int    $type  message type TMessageType::CALL or TMessageType::REPLY
     * @param int    $seqid The sequence id of this message
     */
    public function writeMessageBegin(string $name, int $type, int $seqid): int
    {
        $this->trans_->write(self::LBRACKET);
        $this->pushWriteContext(new ListContext($this));
        $this->writeJSONString($name);
        $this->writeJSONInteger($type);
        $this->writeJSONInteger($seqid);

        return 0;
    }

    /**
     * Close the message
     */
    public function writeMessageEnd(): int
    {
        $this->popWriteContext();
        $this->trans_->write(self::RBRACKET);

        return 0;
    }

    /**
     * Writes a struct header.
     *
     * @param  string     $name Struct name
     */
    public function writeStructBegin(string $name): int
    {
        $this->writeContext_->write();
        $this->trans_->write(self::LBRACE);
        $this->pushWriteContext(new StructContext($this));

        return 0;
    }

    /**
     * Close a struct.
     */
    public function writeStructEnd(): int
    {
        $this->popWriteContext();
        $this->trans_->write(self::RBRACE);

        return 0;
    }

    public function writeFieldBegin(string $fieldName, int $fieldType, int $fieldId): int
    {
        $this->writeJSONString($fieldName);

        return 0;
    }

    public function writeFieldEnd(): int
    {
        return 0;
    }

    public function writeFieldStop(): int
    {
        return 0;
    }

    public function writeMapBegin(int $keyType, int $valType, int $size): int
    {
        $this->assertContextIsNotMapKey(self::NAME_MAP);
        $this->writeContext_->write();
        $this->trans_->write(self::LBRACE);
        $this->pushWriteContext(new MapContext($this));

        return 0;
    }

    public function writeMapEnd(): int
    {
        $this->popWriteContext();
        $this->trans_->write(self::RBRACE);

        return 0;
    }

    public function writeListBegin(int $elemType, int $size): int
    {
        $this->assertContextIsNotMapKey(self::NAME_LIST);
        $this->writeContext_->write();
        $this->trans_->write(self::LBRACKET);
        $this->pushWriteContext(new ListContext($this));
        // No metadata!

        return 0;
    }

    public function writeListEnd(): int
    {
        $this->popWriteContext();
        $this->trans_->write(self::RBRACKET);

        return 0;
    }

    public function writeSetBegin(int $elemType, int $size): int
    {
        $this->assertContextIsNotMapKey(self::NAME_SET);
        $this->writeContext_->write();
        $this->trans_->write(self::LBRACKET);
        $this->pushWriteContext(new ListContext($this));
        // No metadata!

        return 0;
    }

    public function writeSetEnd(): int
    {
        $this->popWriteContext();
        $this->trans_->write(self::RBRACKET);

        return 0;
    }

    public function writeBool(bool $bool): int
    {
        $this->writeJSONInteger($bool ? 1 : 0);

        return 0;
    }

    public function writeByte(int $byte): int
    {
        $this->writeJSONInteger($byte);

        return 0;
    }

    public function writeI16(int $i16): int
    {
        $this->writeJSONInteger($i16);

        return 0;
    }

    public function writeI32(int $i32): int
    {
        $this->writeJSONInteger($i32);

        return 0;
    }

    public function writeI64(int $i64): int
    {
        $this->writeJSONInteger($i64);

        return 0;
    }

    public function writeDouble(float $dub): int
    {
        $this->writeJSONDouble($dub);

        return 0;
    }

    public function writeString(string $str): int
    {
        $this->writeJSONString($str);

        return 0;
    }

    /**
     * Reading methods.
     *
     * simplejson is not meant to be read back into thrift
     * - see http://wiki.apache.org/thrift/ThriftUsageJava
     * - use JSON instead
     */

    public function readMessageBegin(&$name, &$type, &$seqid): int
    {
        throw new TException("Not implemented");
    }

    public function readMessageEnd(): int
    {
        throw new TException("Not implemented");
    }

    public function readStructBegin(&$name): int
    {
        throw new TException("Not implemented");
    }

    public function readStructEnd(): int
    {
        throw new TException("Not implemented");
    }

    public function readFieldBegin(&$name, &$fieldType, &$fieldId): int
    {
        throw new TException("Not implemented");
    }

    public function readFieldEnd(): int
    {
        throw new TException("Not implemented");
    }

    public function readMapBegin(&$keyType, &$valType, &$size): int
    {
        throw new TException("Not implemented");
    }

    public function readMapEnd(): int
    {
        throw new TException("Not implemented");
    }

    public function readListBegin(&$elemType, &$size): int
    {
        throw new TException("Not implemented");
    }

    public function readListEnd(): int
    {
        throw new TException("Not implemented");
    }

    public function readSetBegin(&$elemType, &$size): int
    {
        throw new TException("Not implemented");
    }

    public function readSetEnd(): int
    {
        throw new TException("Not implemented");
    }

    public function readBool(&$bool): int
    {
        throw new TException("Not implemented");
    }

    public function readByte(&$byte): int
    {
        throw new TException("Not implemented");
    }

    public function readI16(&$i16): int
    {
        throw new TException("Not implemented");
    }

    public function readI32(&$i32): int
    {
        throw new TException("Not implemented");
    }

    public function readI64(&$i64): int
    {
        throw new TException("Not implemented");
    }

    public function readDouble(&$dub): int
    {
        throw new TException("Not implemented");
    }

    public function readString(&$str): int
    {
        throw new TException("Not implemented");
    }
}
