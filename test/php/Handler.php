<?php

class Handler implements \ThriftTest\ThriftTestIf
{
    public function testVoid()
    {
        return;
    }

    public function testString($thing)
    {
        return $thing;
    }

    public function testBool($thing)
    {
        return $thing;
    }

    public function testByte($thing)
    {
        return $thing;
    }

    public function testI32($thing)
    {
        return $thing;
    }

    public function testI64($thing)
    {
        return $thing;
    }

    public function testDouble($thing)
    {
        return $thing;
    }

    public function testBinary($thing)
    {
        return $thing;
    }

    public function testStruct(\ThriftTest\Xtruct $thing)
    {
        return $thing;
    }

    public function testNest(\ThriftTest\Xtruct2 $thing)
    {
        return $thing;
    }

    public function testMap(array $thing)
    {
        return $thing;
    }

    public function testStringMap(array $thing)
    {
        return $thing;
    }

    public function testSet(array $thing)
    {
        return $thing;
    }

    public function testList(array $thing)
    {
        return $thing;
    }

    public function testEnum($thing)
    {
        return $thing;
    }

    public function testTypedef($thing)
    {
        return $thing;
    }

    public function testMapMap($hello)
    {
        return [
            -4 => [-4 => -4, -3 => -3, -2 => -2, -1 => -1],
            4 => [4 => 4, 3 => 3, 2 => 2, 1 => 1],
        ];
    }

    public function testInsanity(\ThriftTest\Insanity $argument)
    {
        $result = [];
        $result[1] = [];
        $result[1][\ThriftTest\Numberz::TWO] = $argument;
        $result[1][\ThriftTest\Numberz::THREE] = $argument;
        $result[2] = [];
        $result[2][\ThriftTest\Numberz::SIX] = new \ThriftTest\Insanity();
        return $result;
    }

    public function testMulti($arg0, $arg1, $arg2, array $arg3, $arg4, $arg5)
    {
        $result = new \ThriftTest\Xtruct();
        $result->string_thing = 'Hello2';
        $result->byte_thing = $arg0;
        $result->i32_thing = $arg1;
        $result->i64_thing = $arg2;
        return $result;
    }

    public function testException($arg)
    {
        if ($arg === 'Xception') {
            $e = new \ThriftTest\Xception();
            $e->errorCode = 1001;
            $e->message = 'Xception';
            throw $e;
        }
        if ($arg === 'TException') {
            throw new \Thrift\Exception\TException('TException');
        }
    }

    public function testMultiException($arg0, $arg1)
    {
        if ($arg0 === 'Xception') {
            $e = new \ThriftTest\Xception();
            $e->errorCode = 1001;
            $e->message = 'This is an Xception';
            throw $e;
        }
        if ($arg0 === 'Xception2') {
            $e = new \ThriftTest\Xception2();
            $e->errorCode = 2002;
            $e->struct_thing = new \ThriftTest\Xtruct();
            $e->struct_thing->string_thing = 'This is an Xception2';
            throw $e;
        }
        $result = new \ThriftTest\Xtruct();
        $result->string_thing = $arg1;
        return $result;
    }

    public function testOneway($secondsToSleep)
    {
        sleep($secondsToSleep);
    }
}
