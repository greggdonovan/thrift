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
use Thrift\Type\TType;
use Thrift\Exception\TProtocolException;
use Thrift\Protocol\JSON\BaseContext;
use Thrift\Protocol\JSON\LookaheadReader;
use Thrift\Protocol\JSON\PairContext;
use Thrift\Protocol\JSON\ListContext;

/**
 * JSON implementation of thrift protocol, ported from Java.
 */
class TJSONProtocol extends TProtocol
{
    const COMMA = ',';
    const COLON = ':';
    const LBRACE = '{';
    const RBRACE = '}';
    const LBRACKET = '[';
    const RBRACKET = ']';
    const QUOTE = '"';
    const BACKSLASH = '\\';
    const ZERO = '0';
    const ESCSEQ = '\\';
    const DOUBLEESC = '__DOUBLE_ESCAPE_SEQUENCE__';

    const VERSION = 1;

    public static $JSON_CHAR_TABLE = array(
        /*  0   1   2   3   4   5   6   7   8   9   A   B   C   D   E   F */
        0, 0, 0, 0, 0, 0, 0, 0, 'b', 't', 'n', 0, 'f', 'r', 0, 0, // 0
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // 1
        1, 1, '"', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, // 2
    );

    public static $ESCAPE_CHARS = array('"', '\\', '/', "b", "f", "n", "r", "t");

    public static $ESCAPE_CHAR_VALS = array(
        '"', '\\', '/', "\x08", "\f", "\n", "\r", "\t",
    );

    const NAME_BOOL = "tf";
    const NAME_BYTE = "i8";
    const NAME_I16 = "i16";
    const NAME_I32 = "i32";
    const NAME_I64 = "i64";
    const NAME_DOUBLE = "dbl";
    const NAME_STRUCT = "rec";
    const NAME_STRING = "str";
    const NAME_MAP = "map";
    const NAME_LIST = "lst";
    const NAME_SET = "set";

    private function getTypeNameForTypeID($typeID)
    {
        switch ($typeID) {
            case TType::BOOL:
                return self::NAME_BOOL;
            case TType::BYTE:
                return self::NAME_BYTE;
            case TType::I16:
                return self::NAME_I16;
            case TType::I32:
                return self::NAME_I32;
            case TType::I64:
                return self::NAME_I64;
            case TType::DOUBLE:
                return self::NAME_DOUBLE;
            case TType::STRING:
                return self::NAME_STRING;
            case TType::STRUCT:
                return self::NAME_STRUCT;
            case TType::MAP:
                return self::NAME_MAP;
            case TType::SET:
                return self::NAME_SET;
            case TType::LST:
                return self::NAME_LIST;
            default:
                throw new TProtocolException("Unrecognized type", TProtocolException::UNKNOWN);
        }
    }

    private function getTypeIDForTypeName($name)
    {
        $result = TType::STOP;

        if (strlen((string) $name) > 1) {
            switch (substr($name, 0, 1)) {
                case 'd':
                    $result = TType::DOUBLE;
                    break;
                case 'i':
                    switch (substr($name, 1, 1)) {
                        case '8':
                            $result = TType::BYTE;
                            break;
                        case '1':
                            $result = TType::I16;
                            break;
                        case '3':
                            $result = TType::I32;
                            break;
                        case '6':
                            $result = TType::I64;
                            break;
                    }
                    break;
                case 'l':
                    $result = TType::LST;
                    break;
                case 'm':
                    $result = TType::MAP;
                    break;
                case 'r':
                    $result = TType::STRUCT;
                    break;
                case 's':
                    if (substr($name, 1, 1) == 't') {
                        $result = TType::STRING;
                    } elseif (substr($name, 1, 1) == 'e') {
                        $result = TType::SET;
                    }
                    break;
                case 't':
                    $result = TType::BOOL;
                    break;
            }
        }
        if ($result == TType::STOP) {
            throw new TProtocolException("Unrecognized type", TProtocolException::INVALID_DATA);
        }

        return $result;
    }

    public $contextStack_ = array();
    public $context_;
    public $reader_;

    private function pushContext($c)
    {
        array_push($this->contextStack_, $this->context_);
        $this->context_ = $c;
    }

    private function popContext()
    {
        $this->context_ = array_pop($this->contextStack_);
    }

    public function __construct($trans)
    {
        parent::__construct($trans);
        $this->context_ = new BaseContext();
        $this->reader_ = new LookaheadReader($this);
    }

    public function reset()
    {
        $this->contextStack_ = array();
        $this->context_ = new BaseContext();
        $this->reader_ = new LookaheadReader($this);
    }

    public function readJSONSyntaxChar($b)
    {
        $ch = $this->reader_->read();

        if (substr($ch, 0, 1) != $b) {
            throw new TProtocolException("Unexpected character: " . $ch, TProtocolException::INVALID_DATA);
        }
    }

    private function writeJSONString($b)
    {
        $this->context_->write();

        if (is_numeric($b) && $this->context_->escapeNum()) {
            $this->trans_->write(self::QUOTE);
        }

        $this->trans_->write(json_encode($b, JSON_UNESCAPED_UNICODE));

        if (is_numeric($b) && $this->context_->escapeNum()) {
            $this->trans_->write(self::QUOTE);
        }
    }

    private function writeJSONInteger($num)
    {
        $this->context_->write();

        if ($this->context_->escapeNum()) {
            $this->trans_->write(self::QUOTE);
        }

        $this->trans_->write($num);

        if ($this->context_->escapeNum()) {
            $this->trans_->write(self::QUOTE);
        }
    }

    private function writeJSONDouble($num)
    {
        $this->context_->write();

        if ($this->context_->escapeNum()) {
            $this->trans_->write(self::QUOTE);
        }

        #TODO add compatibility with NAN and INF
        $this->trans_->write(json_encode($num));

        if ($this->context_->escapeNum()) {
            $this->trans_->write(self::QUOTE);
        }
    }

    private function writeJSONObjectStart()
    {
        $this->context_->write();
        $this->trans_->write(self::LBRACE);
        $this->pushContext(new PairContext($this));
    }

    private function writeJSONObjectEnd()
    {
        $this->popContext();
        $this->trans_->write(self::RBRACE);
    }

    private function writeJSONArrayStart()
    {
        $this->context_->write();
        $this->trans_->write(self::LBRACKET);
        $this->pushContext(new ListContext($this));
    }

    private function writeJSONArrayEnd()
    {
        $this->popContext();
        $this->trans_->write(self::RBRACKET);
    }

    private function readJSONString($skipContext)
    {
        if (!$skipContext) {
            $this->context_->read();
        }

        $jsonString = '';
        $lastChar = null;
        while (true) {
            $ch = $this->reader_->read();
            $jsonString .= $ch;
            if ($ch == self::QUOTE &&
                $lastChar !== null &&
                $lastChar !== self::ESCSEQ) {
                break;
            }
            if ($ch == self::ESCSEQ && $lastChar == self::ESCSEQ) {
                $lastChar = self::DOUBLEESC;
            } else {
                $lastChar = $ch;
            }
        }

        return json_decode($jsonString);
    }

    private function isJSONNumeric($b)
    {
        switch ($b) {
            case '+':
            case '-':
            case '.':
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
            case 'E':
            case 'e':
                return true;
        }

        return false;
    }

    private function readJSONNumericChars()
    {
        $strbld = array();

        while (true) {
            $ch = $this->reader_->peek();

            if (!$this->isJSONNumeric($ch)) {
                break;
            }

            $strbld[] = $this->reader_->read();
        }

        return implode("", $strbld);
    }

    private function readJSONInteger()
    {
        $this->context_->read();

        if ($this->context_->escapeNum()) {
            $this->readJSONSyntaxChar(self::QUOTE);
        }

        $str = $this->readJSONNumericChars();

        if ($this->context_->escapeNum()) {
            $this->readJSONSyntaxChar(self::QUOTE);
        }

        if (!is_numeric($str)) {
            throw new TProtocolException("Invalid data in numeric: " . $str, TProtocolException::INVALID_DATA);
        }

        return intval($str);
    }

    /**
     * Identical to readJSONInteger but without the final cast.
     * Needed for proper handling of i64 on 32 bit machines.  Why a
     * separate function?  So we don't have to force the rest of the
     * use cases through the extra conditional.
     */
    private function readJSONIntegerAsString()
    {
        $this->context_->read();

        if ($this->context_->escapeNum()) {
            $this->readJSONSyntaxChar(self::QUOTE);
        }

        $str = $this->readJSONNumericChars();

        if ($this->context_->escapeNum()) {
            $this->readJSONSyntaxChar(self::QUOTE);
        }

        if (!is_numeric($str)) {
            throw new TProtocolException("Invalid data in numeric: " . $str, TProtocolException::INVALID_DATA);
        }

        return $str;
    }

    private function readJSONDouble()
    {
        $this->context_->read();

        if (substr($this->reader_->peek(), 0, 1) == self::QUOTE) {
            $arr = $this->readJSONString(true);

            if ($arr == "NaN") {
                return NAN;
            } elseif ($arr == "Infinity") {
                return INF;
            } elseif (!$this->context_->escapeNum()) {
                throw new TProtocolException(
                    "Numeric data unexpectedly quoted " . $arr,
                    TProtocolException::INVALID_DATA
                );
            }

            return floatval($arr);
        } else {
            if ($this->context_->escapeNum()) {
                $this->readJSONSyntaxChar(self::QUOTE);
            }

            return floatval($this->readJSONNumericChars());
        }
    }

    private function readJSONObjectStart()
    {
        $this->context_->read();
        $this->readJSONSyntaxChar(self::LBRACE);
        $this->pushContext(new PairContext($this));
    }

    private function readJSONObjectEnd()
    {
        $this->readJSONSyntaxChar(self::RBRACE);
        $this->popContext();
    }

    private function readJSONArrayStart()
    {
        $this->context_->read();
        $this->readJSONSyntaxChar(self::LBRACKET);
        $this->pushContext(new ListContext($this));
    }

    private function readJSONArrayEnd()
    {
        $this->readJSONSyntaxChar(self::RBRACKET);
        $this->popContext();
    }

    /**
     * Writes the message header
     *
     * @param string $name Function name
     * @param int $type message type TMessageType::CALL or TMessageType::REPLY
     * @param int $seqid The sequence id of this message
     */
    public function writeMessageBegin(string $name, int $type, int $seqid): int
    {
        $this->writeJSONArrayStart();
        $this->writeJSONInteger(self::VERSION);
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
        $this->writeJSONArrayEnd();

        return 0;
    }

    /**
     * Writes a struct header.
     *
     * @param  string $name Struct name
     * @throws TException on write error
     * @return int        How many bytes written
     */
    public function writeStructBegin(string $name): int
    {
        $this->writeJSONObjectStart();

        return 0;
    }

    /**
     * Close a struct.
     *
     * @throws TException on write error
     * @return int        How many bytes written
     */
    public function writeStructEnd(): int
    {
        $this->writeJSONObjectEnd();

        return 0;
    }

    public function writeFieldBegin(string $fieldName, int $fieldType, int $fieldId): int
    {
        $this->writeJSONInteger($fieldId);
        $this->writeJSONObjectStart();
        $this->writeJSONString($this->getTypeNameForTypeID($fieldType));

        return 0;
    }

    public function writeFieldEnd(): int
    {
        $this->writeJsonObjectEnd();

        return 0;
    }

    public function writeFieldStop(): int
    {
        return 0;
    }

    public function writeMapBegin(int $keyType, int $valType, int $size): int
    {
        $this->writeJSONArrayStart();
        $this->writeJSONString($this->getTypeNameForTypeID($keyType));
        $this->writeJSONString($this->getTypeNameForTypeID($valType));
        $this->writeJSONInteger($size);
        $this->writeJSONObjectStart();

        return 0;
    }

    public function writeMapEnd(): int
    {
        $this->writeJSONObjectEnd();
        $this->writeJSONArrayEnd();

        return 0;
    }

    public function writeListBegin(int $elemType, int $size): int
    {
        $this->writeJSONArrayStart();
        $this->writeJSONString($this->getTypeNameForTypeID($elemType));
        $this->writeJSONInteger($size);

        return 0;
    }

    public function writeListEnd(): int
    {
        $this->writeJSONArrayEnd();

        return 0;
    }

    public function writeSetBegin(int $elemType, int $size): int
    {
        $this->writeJSONArrayStart();
        $this->writeJSONString($this->getTypeNameForTypeID($elemType));
        $this->writeJSONInteger($size);

        return 0;
    }

    public function writeSetEnd(): int
    {
        $this->writeJSONArrayEnd();

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
     * Reads the message header
     *
     * @param string $name Function name
     * @param int $type message type TMessageType::CALL or TMessageType::REPLY
     * @parem int $seqid The sequence id of this message
     */
    public function readMessageBegin(&$name, &$type, &$seqid): int
    {
        $this->readJSONArrayStart();

        if ($this->readJSONInteger() != self::VERSION) {
            throw new TProtocolException("Message contained bad version", TProtocolException::BAD_VERSION);
        }

        $name = $this->readJSONString(false);
        $type = $this->readJSONInteger();
        $seqid = $this->readJSONInteger();

        return 0;
    }

    /**
     * Read the close of message
     */
    public function readMessageEnd(): int
    {
        $this->readJSONArrayEnd();

        return 0;
    }

    public function readStructBegin(&$name): int
    {
        $this->readJSONObjectStart();

        return 0;
    }

    public function readStructEnd(): int
    {
        $this->readJSONObjectEnd();

        return 0;
    }

    public function readFieldBegin(&$name, &$fieldType, &$fieldId): int
    {
        $ch = $this->reader_->peek();
        $name = "";

        if (substr($ch, 0, 1) == self::RBRACE) {
            $fieldType = TType::STOP;
        } else {
            $fieldId = $this->readJSONInteger();
            $this->readJSONObjectStart();
            $fieldType = $this->getTypeIDForTypeName($this->readJSONString(false));
        }

        return 0;
    }

    public function readFieldEnd(): int
    {
        $this->readJSONObjectEnd();

        return 0;
    }

    public function readMapBegin(&$keyType, &$valType, &$size): int
    {
        $this->readJSONArrayStart();
        $keyType = $this->getTypeIDForTypeName($this->readJSONString(false));
        $valType = $this->getTypeIDForTypeName($this->readJSONString(false));
        $size = $this->readJSONInteger();
        $this->readJSONObjectStart();

        return 0;
    }

    public function readMapEnd(): int
    {
        $this->readJSONObjectEnd();
        $this->readJSONArrayEnd();

        return 0;
    }

    public function readListBegin(&$elemType, &$size): int
    {
        $this->readJSONArrayStart();
        $elemType = $this->getTypeIDForTypeName($this->readJSONString(false));
        $size = $this->readJSONInteger();

        return 0;
    }

    public function readListEnd(): int
    {
        $this->readJSONArrayEnd();

        return 0;
    }

    public function readSetBegin(&$elemType, &$size): int
    {
        $this->readJSONArrayStart();
        $elemType = $this->getTypeIDForTypeName($this->readJSONString(false));
        $size = $this->readJSONInteger();

        return 0;
    }

    public function readSetEnd(): int
    {
        $this->readJSONArrayEnd();

        return 0;
    }

    public function readBool(&$bool): int
    {
        $bool = $this->readJSONInteger() == 0 ? false : true;

        return 0;
    }

    public function readByte(&$byte): int
    {
        $byte = $this->readJSONInteger();

        return 0;
    }

    public function readI16(&$i16): int
    {
        $i16 = $this->readJSONInteger();

        return 0;
    }

    public function readI32(&$i32): int
    {
        $i32 = $this->readJSONInteger();

        return 0;
    }

    public function readI64(&$i64): int
    {
        if (PHP_INT_SIZE === 4) {
            $i64 = $this->readJSONIntegerAsString();
        } else {
            $i64 = $this->readJSONInteger();
        }

        return 0;
    }

    public function readDouble(&$dub): int
    {
        $dub = $this->readJSONDouble();

        return 0;
    }

    public function readString(&$str): int
    {
        $str = $this->readJSONString(false);

        return 0;
    }
}
