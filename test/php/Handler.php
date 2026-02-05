<?php

class Handler implements \ThriftTest\ThriftTestIf
{
    public function testVoid(): void
    {
        return;
    }

    public function testString(string $thing): string
    {
        return $thing;
    }

    public function testBool(bool $thing): bool
    {
        return $thing;
    }

    public function testByte(int $thing): int
    {
        return $thing;
    }

    public function testI32(int $thing): int
    {
        return $thing;
    }

    public function testI64(int $thing): int
    {
        return $thing;
    }

    public function testDouble(float $thing): float
    {
        return $thing;
    }

    public function testBinary(string $thing): string
    {
        return $thing;
    }

    public function testStruct(\ThriftTest\Xtruct $thing): \ThriftTest\Xtruct
    {
        return $thing;
    }

    public function testNest(\ThriftTest\Xtruct2 $thing): \ThriftTest\Xtruct2
    {
        return $thing;
    }

    public function testMap(array $thing): array
    {
        return $thing;
    }

    public function testStringMap(array $thing): array
    {
        return $thing;
    }

    public function testSet(array $thing): array
    {
        return $thing;
    }

    public function testList(array $thing): array
    {
        return $thing;
    }

    public function testEnum(int $thing): int
    {
        return $thing;
    }

    public function testTypedef(int $thing): int
    {
        return $thing;
    }

    public function testMapMap(int $hello): array
    {
        return [
            -4 => [-4 => -4, -3 => -3, -2 => -2, -1 => -1],
            4 => [4 => 4, 3 => 3, 2 => 2, 1 => 1],
        ];
    }

    public function testInsanity(\ThriftTest\Insanity $argument): array
    {
        $result = [];
        $result[1] = [];
        $result[1][\ThriftTest\Numberz::TWO] = $argument;
        $result[1][\ThriftTest\Numberz::THREE] = $argument;
        $result[2] = [];
        $result[2][\ThriftTest\Numberz::SIX] = new \ThriftTest\Insanity();
        return $result;
    }

    public function testMulti(
        int $arg0,
        int $arg1,
        int $arg2,
        array $arg3,
        int $arg4,
        int $arg5
    ): \ThriftTest\Xtruct {
        $result = new \ThriftTest\Xtruct();
        $result->string_thing = 'Hello2';
        $result->byte_thing = $arg0;
        $result->i32_thing = $arg1;
        $result->i64_thing = $arg2;
        return $result;
    }

    public function testException(string $arg): void
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

    public function testMultiException(string $arg0, string $arg1): \ThriftTest\Xtruct
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

    public function testOneway(int $secondsToSleep): void
    {
        sleep($secondsToSleep);
    }
}
