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

namespace Thrift\Type;

/**
 * Data types that can be sent via Thrift
 */
final class TType
{
    public const int STOP   = 0;
    public const int VOID   = 1;
    public const int BOOL   = 2;
    public const int BYTE   = 3;
    public const int I08    = 3;
    public const int DOUBLE = 4;
    public const int I16    = 6;
    public const int I32    = 8;
    public const int I64    = 10;
    public const int STRING = 11;
    public const int UTF7   = 11;
    public const int STRUCT = 12;
    public const int MAP    = 13;
    public const int SET    = 14;
    public const int LST    = 15;    // N.B. cannot use LIST keyword in PHP!
    public const int UTF8   = 16;
    public const int UTF16  = 17;

    private function __construct()
    {
    }
}
