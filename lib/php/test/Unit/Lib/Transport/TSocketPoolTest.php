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
 */

namespace Test\Thrift\Unit\Lib\Transport;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thrift\Exception\TException;
use Thrift\Transport\TSocketPool;

class TSocketPoolTest extends TestCase
{
    use PHPMock;

    /**
     * Helper method to properly evaluate PHPUnit constraints when comparing expected vs actual args.
     */
    private function assertArgsMatch(array $expected, array $actual): void
    {
        $this->assertCount(count($expected), $actual);
        foreach ($expected as $i => $exp) {
            if ($exp instanceof \PHPUnit\Framework\Constraint\Constraint) {
                $this->assertThat($actual[$i], $exp);
            } else {
                $this->assertEquals($exp, $actual[$i]);
            }
        }
    }

    protected function setUp(): void
    {
        #need to be defined before the TSocketPool class definition
        self::defineFunctionMock('Thrift\Transport', 'function_exists');
    }

    #[DataProvider('constructDataProvider')]
    public function testConstruct(
        $hosts,
        $ports,
        $persist,
        $debugHandler,
        $expectedServers
    ) {
        $socketPool = new TSocketPool($hosts, $ports, $persist, $debugHandler);

        $ref = new \ReflectionObject($socketPool);
        $serversProp = $ref->getProperty('servers_');

        $this->assertEquals($expectedServers, $serversProp->getValue($socketPool));
    }


    public static function constructDataProvider()
    {
        yield 'one server' => [
            ['localhost'],
            [9090],
            false,
            null,
            [
                ['host' => 'localhost', 'port' => 9090],
            ],
        ];
        yield 'two servers' => [
            ['localhost1', 'localhost2'],
            [9090, 9091],
            false,
            null,
            [
                ['host' => 'localhost1', 'port' => 9090],
                ['host' => 'localhost2', 'port' => 9091],
            ],
        ];
        yield 'one server with one port' => [
            ['localhost'],
            9090,
            false,
            null,
            [
                ['host' => 'localhost', 'port' => 9090],
            ],
        ];
        yield 'two servers with one port' => [
            ['localhost1', 'localhost2'],
            9090,
            false,
            null,
            [
                ['host' => 'localhost1', 'port' => 9090],
                ['host' => 'localhost2', 'port' => 9090],
            ],
        ];
    }

    public function testAddServer(): void
    {
        $socketPool = new TSocketPool([], []);
        $socketPool->addServer('localhost', 9090);

        $ref = new \ReflectionObject($socketPool);
        $servers = $ref->getProperty('servers_');

        $this->assertEquals([['host' => 'localhost', 'port' => 9090]], $servers->getValue($socketPool));
    }

    public function testSetNumRetries(): void
    {
        $socketPool = new TSocketPool([], []);
        $socketPool->setNumRetries(5);

        $ref = new \ReflectionObject($socketPool);
        $numRetries = $ref->getProperty('numRetries_');

        $this->assertEquals(5, $numRetries->getValue($socketPool));
    }

    public function testrSetRetryInterval(): void
    {
        $socketPool = new TSocketPool([], []);
        $socketPool->setRetryInterval(5);

        $ref = new \ReflectionObject($socketPool);
        $retryInterval = $ref->getProperty('retryInterval_');

        $this->assertEquals(5, $retryInterval->getValue($socketPool));
    }

    public function testrSetMaxConsecutiveFailures(): void
    {
        $socketPool = new TSocketPool([], []);
        $socketPool->setMaxConsecutiveFailures(5);

        $ref = new \ReflectionObject($socketPool);
        $maxConsecutiveFailures = $ref->getProperty('maxConsecutiveFailures_');

        $this->assertEquals(5, $maxConsecutiveFailures->getValue($socketPool));
    }

    public function testrSetRandomize(): void
    {
        $socketPool = new TSocketPool([], []);
        $socketPool->setRandomize(false);

        $ref = new \ReflectionObject($socketPool);
        $randomize = $ref->getProperty('randomize_');

        $this->assertEquals(false, $randomize->getValue($socketPool));
    }

    public function testrSetAlwaysTryLast(): void
    {
        $socketPool = new TSocketPool([], []);
        $socketPool->setAlwaysTryLast(false);

        $ref = new \ReflectionObject($socketPool);
        $alwaysTryLast = $ref->getProperty('alwaysTryLast_');

        $this->assertEquals(false, $alwaysTryLast->getValue($socketPool));
    }

    #[DataProvider('openDataProvider')]
    public function testOpen(
        $hosts,
        $ports,
        $persist,
        $debugHandler,
        $randomize,
        $retryInterval,
        $numRetries,
        $maxConsecutiveFailures,
        $debug,
        $servers,
        $functionExistCallParams,
        $functionExistResult,
        $apcuFetchCallParams,
        $apcuFetchResult,
        $timeResult,
        $debugHandlerCall,
        $apcuStoreCallParams,
        $fsockopenCallParams,
        $fsockopenResult,
        $expectedException,
        $expectedExceptionMessage
    ) {
        $functionExistsCallIndex = 0;
        $this->getFunctionMock('Thrift\Transport', 'function_exists')
             ->expects($this->exactly(count($functionExistCallParams)))
             ->willReturnCallback(function (...$args) use (&$functionExistsCallIndex, $functionExistCallParams, $functionExistResult) {
                 $this->assertArgsMatch($functionExistCallParams[$functionExistsCallIndex], $args);
                 return $functionExistResult[$functionExistsCallIndex++];
             });

        $this->getFunctionMock('Thrift\Transport', 'shuffle')
             ->expects($randomize ? $this->once() : $this->never())
             ->with($servers)
             ->willReturnCallback(function (array &$servers) {
                 $servers = array_reverse($servers);

                 return true;
             });

        $apcuFetchCallIndex = 0;
        $this->getFunctionMock('Thrift\Transport', 'apcu_fetch')
             ->expects($this->exactly(count($apcuFetchCallParams)))
             ->willReturnCallback(function (...$args) use (&$apcuFetchCallIndex, $apcuFetchCallParams, $apcuFetchResult) {
                 $this->assertArgsMatch($apcuFetchCallParams[$apcuFetchCallIndex], $args);
                 return $apcuFetchResult[$apcuFetchCallIndex++];
             });

        $callUserFuncCallIndex = 0;
        $this->getFunctionMock('Thrift\Transport', 'call_user_func')
             ->expects($this->exactly(count($debugHandlerCall)))
             ->willReturnCallback(function (...$args) use (&$callUserFuncCallIndex, $debugHandlerCall) {
                 $this->assertArgsMatch($debugHandlerCall[$callUserFuncCallIndex], $args);
                 $callUserFuncCallIndex++;
                 return true;
             });

        $apcuStoreCallIndex = 0;
        $this->getFunctionMock('Thrift\Transport', 'apcu_store')
             ->expects($this->exactly(count($apcuStoreCallParams)))
             ->willReturnCallback(function (...$args) use (&$apcuStoreCallIndex, $apcuStoreCallParams) {
                 $this->assertArgsMatch($apcuStoreCallParams[$apcuStoreCallIndex], $args);
                 $apcuStoreCallIndex++;
                 return true;
             });

        $this->getFunctionMock('Thrift\Transport', 'time')
             ->expects($this->exactly(count($timeResult)))
             ->willReturnOnConsecutiveCalls(...$timeResult);

        #due to the running tests in separate process we could not open stream in data provider, so we need to do it here
        foreach ($fsockopenResult as $num => $result) {
            $fsockopenResult[$num] = $result ? fopen(...$result) : $result;
        }

        $fsockopenCallIndex = 0;
        $this->getFunctionMock('Thrift\Transport', $persist ? 'pfsockopen' : 'fsockopen')
             ->expects($this->exactly(count($fsockopenCallParams)))
             ->willReturnCallback(function (...$args) use (&$fsockopenCallIndex, $fsockopenCallParams, $fsockopenResult) {
                 $this->assertArgsMatch($fsockopenCallParams[$fsockopenCallIndex], $args);
                 return $fsockopenResult[$fsockopenCallIndex++];
             });

        $this->getFunctionMock('Thrift\Transport', 'socket_import_stream')
             ->expects(is_null($expectedException) ? $this->once() : $this->never())
             ->with(
                 $this->callback(function ($stream) {
                     return is_resource($stream);
                 })
             )
             ->willReturn(true);

        $this->getFunctionMock('Thrift\Transport', 'socket_set_option')
             ->expects(is_null($expectedException) ? $this->once() : $this->never())
             ->with(
                 self::anything(), #$socket,
                 SOL_TCP, #$level
                 TCP_NODELAY, #$option
                 1 #$value
             )
             ->willReturn(true);

        if ($expectedException) {
            $this->expectException($expectedException);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $socketPool = new TSocketPool($hosts, $ports, $persist, $debugHandler);
        $socketPool->setRandomize($randomize);
        $socketPool->setRetryInterval($retryInterval);
        $socketPool->setNumRetries($numRetries);
        $socketPool->setMaxConsecutiveFailures($maxConsecutiveFailures);
        $socketPool->setDebug($debug);

        $this->assertNull($socketPool->open());
    }

    public static function openDataProvider()
    {
        $default = [
            'hosts' => ['localhost'],
            'ports' => [9090],
            'persist' => false,
            'debugHandler' => null,
            'randomize' => true,
            'retryInterval' => 5,
            'numRetries' => 1,
            'maxConsecutiveFailures' => 1,
            'debug' => false,
            'servers' => [
                ['host' => 'localhost', 'port' => 9090],
            ],
            'functionExistCallParams' => [
                ['apcu_fetch'],
                ['socket_import_stream'],
                ['socket_set_option'],
            ],
            'functionExistResult' => [
                true,
                true,
                true,
            ],
            'apcuFetchCallParams' => [
                ['thrift_failtime:localhost:9090~', self::anything()],
            ],
            'apcuFetchResult' => [
                false,
            ],
            'timeResult' => [],
            'debugHandlerCall' => [],
            'apcuStoreCallParams' => [],
            'fsockopenCallParams' => [
                [
                    'localhost',
                    9090,
                    self::anything(), #$errno,
                    self::anything(), #$errstr,
                    self::anything(), #$this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000),
                ],
            ],
            'fsockopenResult' => [
                ['php://temp', 'r'],
            ],
            'expectedException' => null,
            'expectedExceptionMessage' => null,
        ];

        yield 'one server ready' => $default;
        yield 'one server failed' => array_merge(
            $default,
            [
                'functionExistCallParams' => [
                    ['apcu_fetch'],
                ],
                'fsockopenResult' => [
                    false,
                ],
                'apcuFetchCallParams' => [
                    ['thrift_failtime:localhost:9090~', self::anything()],
                    ['thrift_consecfails:localhost:9090~', self::anything()],
                ],
                'apcuFetchResult' => [
                    false,
                    0,
                ],
                'apcuStoreCallParams' => [
                    ['thrift_failtime:localhost:9090~', self::anything(), 0],
                    ['thrift_consecfails:localhost:9090~', 0, 0],
                ],
                'timeResult' => [
                    1,
                ],
                'expectedException' => TException::class,
                'expectedExceptionMessage' => 'TSocketPool: All hosts in pool are down. (localhost:9090)',
            ]
        );
        yield 'connect to one server on second attempt' => array_merge(
            $default,
            [
                'numRetries' => 2,
                'fsockopenCallParams' => [
                    [
                        'localhost',
                        9090,
                        self::anything(), #$errno,
                        self::anything(), #$errstr,
                        self::anything(), #$this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000),
                    ],
                    [
                        'localhost',
                        9090,
                        self::anything(), #$errno,
                        self::anything(), #$errstr,
                        self::anything(), #$this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000),
                    ],
                ],
                'fsockopenResult' => [
                    false,
                    ['php://temp', 'r'],
                ],
                'apcuStoreCallParams' => [],
            ]
        );
        yield 'last time fail time is not expired' => array_merge(
            $default,
            [
                'retryInterval' => 5,
                'apcuFetchResult' => [
                    99,
                ],
                'apcuStoreCallParams' => [
                    ['thrift_failtime:localhost:9090~', 0, 0],
                ],
                'timeResult' => [
                    100,
                ],
            ]
        );
        yield 'last time fail time is expired, store info to debug' => array_merge(
            $default,
            [
                'retryInterval' => 5,
                'apcuFetchResult' => [
                    90,
                ],
                'apcuStoreCallParams' => [
                    ['thrift_failtime:localhost:9090~', 0, 0],
                ],
                'timeResult' => [
                    100,
                ],
                'debug' => true,
                'debugHandlerCall' => [
                    ['error_log', 'TSocketPool: retryInterval (5) has passed for host localhost:9090'],
                ],
            ]
        );
        yield 'not accessible server, store info to debug' => array_merge(
            $default,
            [
                'retryInterval' => 5,
                'functionExistCallParams' => [
                    ['apcu_fetch'],
                ],
                'functionExistResult' => [
                    true,
                ],
                'apcuFetchCallParams' => [
                    ['thrift_failtime:localhost:9090~', self::anything()],
                    ['thrift_consecfails:localhost:9090~', self::anything()],
                ],
                'apcuFetchResult' => [
                    90,
                    0,
                ],
                'apcuStoreCallParams' => [
                    ['thrift_failtime:localhost:9090~', self::anything(), 0],
                    ['thrift_consecfails:localhost:9090~', 0, 0],
                ],
                'timeResult' => [
                    100,
                    101,
                ],
                'fsockopenResult' => [
                    false,
                ],
                'debug' => true,
                'debugHandlerCall' => [
                    ['error_log', 'TSocketPool: retryInterval (5) has passed for host localhost:9090'],
                    ['error_log', 'TSocket: Could not connect to localhost:9090 ( [])'],
                    ['error_log', 'TSocketPool: marking localhost:9090 as down for 5 secs after 1 failed attempts.'],
                    ['error_log', 'TSocketPool: All hosts in pool are down. (localhost:9090)'],
                ],
                'expectedException' => TException::class,
                'expectedExceptionMessage' => 'TSocketPool: All hosts in pool are down. (localhost:9090)',
            ]
        );
        yield 'max consecutive failures' => array_merge(
            $default,
            [
                'maxConsecutiveFailures' => 5,
                'functionExistCallParams' => [
                    ['apcu_fetch'],
                ],
                'functionExistResult' => [
                    true,
                ],
                'apcuFetchCallParams' => [
                    ['thrift_failtime:localhost:9090~', self::anything()],
                    ['thrift_consecfails:localhost:9090~', self::anything()],
                ],
                'apcuFetchResult' => [
                    false,
                    0,
                ],
                'apcuStoreCallParams' => [
                    ['thrift_consecfails:localhost:9090~', 1, 0],
                ],
                'timeResult' => [],
                'fsockopenResult' => [
                    false,
                ],
                'expectedException' => TException::class,
                'expectedExceptionMessage' => 'TSocketPool: All hosts in pool are down. (localhost:9090)',
            ]
        );
        yield 'apcu disabled' => array_merge(
            $default,
            [
                'functionExistCallParams' => [
                    ['apcu_fetch'],
                ],
                'functionExistResult' => [
                    false,
                ],
                'fsockopenResult' => [
                    false,
                ],
                'timeResult' => [
                    1,
                ],
                'apcuFetchCallParams' => [],
                'apcuStoreCallParams' => [],
                'expectedException' => TException::class,
                'expectedExceptionMessage' => 'TSocketPool: All hosts in pool are down. (localhost:9090)',
            ]
        );
        yield 'second host accessible' => array_merge(
            $default,
            [
                'hosts' => ['host1', 'host2'],
                'ports' => [9090, 9091],
                'servers' => [
                    ['host' => 'host1', 'port' => 9090],
                    ['host' => 'host2', 'port' => 9091],
                ],
                'fsockopenCallParams' => [
                    [
                        'host2',
                        9091,
                        self::anything(), #$errno,
                        self::anything(), #$errstr,
                        self::anything(), #$this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000),
                    ],
                    [
                        'host1',
                        9090,
                        self::anything(), #$errno,
                        self::anything(), #$errstr,
                        self::anything(), #$this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000),
                    ],
                ],
                'fsockopenResult' => [
                    false,
                    ['php://temp', 'r'],
                ],
                'apcuFetchCallParams' => [
                    ['thrift_failtime:host2:9091~', self::anything()],
                    ['thrift_consecfails:host2:9091~', self::anything()],
                    ['thrift_failtime:host1:9090~', self::anything()],
                ],
                'apcuFetchResult' => [
                    false,
                    0,
                    false,
                ],
                'apcuStoreCallParams' => [
                    ['thrift_failtime:host2:9091~', self::anything(), 0],
                    ['thrift_consecfails:host2:9091~', 0, 0],
                ],
                'timeResult' => [
                    1,
                ],
            ]
        );
    }
}
