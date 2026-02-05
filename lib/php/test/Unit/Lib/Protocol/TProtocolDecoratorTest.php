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

namespace Test\Thrift\Unit\Lib\Protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thrift\Protocol\TProtocol;
use Thrift\Protocol\TProtocolDecorator;

class TProtocolDecoratorTest extends TestCase
{

    #[DataProvider('methodDecorationDataProvider')]
    public function testMethodDecoration(
        $methodName,
        $methodArguments
    ) {
        $concreteProtocol = $this->createMock(TProtocol::class);
        $decorator = new class ($concreteProtocol) extends TProtocolDecorator {
            public function __construct(TProtocol $protocol)
            {
                parent::__construct($protocol);
            }
        };

        $concreteProtocol->expects($this->once())
                         ->method($methodName)
                         ->with(...$methodArguments);

        $decorator->$methodName(...$methodArguments);
    }

    public static function methodDecorationDataProvider()
    {
        // Write methods with proper typed arguments
        yield 'writeMessageBegin' => ['writeMessageBegin', ['testName', 1, 100]];
        yield 'writeMessageEnd' => ['writeMessageEnd', []];
        yield 'writeStructBegin' => ['writeStructBegin', ['structName']];
        yield 'writeStructEnd' => ['writeStructEnd', []];
        yield 'writeFieldBegin' => ['writeFieldBegin', ['fieldName', 1, 1]];
        yield 'writeFieldEnd' => ['writeFieldEnd', []];
        yield 'writeFieldStop' => ['writeFieldStop', []];
        yield 'writeMapBegin' => ['writeMapBegin', [1, 2, 10]];
        yield 'writeMapEnd' => ['writeMapEnd', []];
        yield 'writeListBegin' => ['writeListBegin', [1, 10]];
        yield 'writeListEnd' => ['writeListEnd', []];
        yield 'writeSetBegin' => ['writeSetBegin', [1, 10]];
        yield 'writeSetEnd' => ['writeSetEnd', []];
        yield 'writeBool' => ['writeBool', [true]];
        yield 'writeByte' => ['writeByte', [1]];
        yield 'writeI16' => ['writeI16', [100]];
        yield 'writeI32' => ['writeI32', [1000]];
        yield 'writeI64' => ['writeI64', [10000]];
        yield 'writeDouble' => ['writeDouble', [1.5]];
        yield 'writeString' => ['writeString', ['testString']];
        // Read methods use reference parameters passed by value in test
        $name = null;
        $type = null;
        $seqid = null;
        $size = null;
        $value = null;
        yield 'readMessageBegin' => ['readMessageBegin', [&$name, &$type, &$seqid]];
        yield 'readMessageEnd' => ['readMessageEnd', []];
        yield 'readStructBegin' => ['readStructBegin', [&$name]];
        yield 'readStructEnd' => ['readStructEnd', []];
        yield 'readFieldBegin' => ['readFieldBegin', [&$name, &$type, &$seqid]];
        yield 'readFieldEnd' => ['readFieldEnd', []];
        yield 'readMapBegin' => ['readMapBegin', [&$type, &$seqid, &$size]];
        yield 'readMapEnd' => ['readMapEnd', []];
        yield 'readListBegin' => ['readListBegin', [&$type, &$size]];
        yield 'readListEnd' => ['readListEnd', []];
        yield 'readSetBegin' => ['readSetBegin', [&$type, &$size]];
        yield 'readSetEnd' => ['readSetEnd', []];
        yield 'readBool' => ['readBool', [&$value]];
        yield 'readByte' => ['readByte', [&$value]];
        yield 'readI16' => ['readI16', [&$value]];
        yield 'readI32' => ['readI32', [&$value]];
        yield 'readI64' => ['readI64', [&$value]];
        yield 'readDouble' => ['readDouble', [&$value]];
        yield 'readString' => ['readString', [&$value]];
    }
}
