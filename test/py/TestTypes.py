#!/usr/bin/env python3
#
# Licensed to the Apache Software Foundation (ASF) under one
# or more contributor license agreements. See the NOTICE file
# distributed with this work for additional information
# regarding copyright ownership. The ASF licenses this file
# to you under the Apache License, Version 2.0 (the
# "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing,
# software distributed under the License is distributed on an
# "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
# KIND, either express or implied. See the License for the
# specific language governing permissions and limitations
# under the License.
from ThriftTest import ThriftTest
from ThriftTest.ThriftTest import Client
from ThriftTest.ttypes import Xtruct

import unittest
import typing
from uuid import UUID

# only run this test if the string 'options string: py:type_hints' esxists in the file 
def has_type_hints_option():
    with open(ThriftTest.__file__) as f:
        return 'options string: py:type_hints' in f.read()

if has_type_hints_option():
    from TypeHintsTest.TypeHintsTest import Client as TypeHintsClient
    from TypeHintsTest.ttypes import Container, Inner, Payload, Status

@unittest.skipUnless(has_type_hints_option(), "type hints not enabled")
class TypeAnnotationsTest(unittest.TestCase):

    def test_void(self):
        self.assertEqual(typing.get_type_hints(Client.testVoid), {'return': None})

    def test_string(self):
        self.assertEqual(typing.get_type_hints(Client.testString), {'return': str, 'thing': str})

    def test_byte(self):
        self.assertEqual(typing.get_type_hints(Client.testByte), {'return': int, 'thing': int})

    def test_i32(self):
        self.assertEqual(typing.get_type_hints(Client.testI32), {'return': int, 'thing': int})

    def test_i64(self):
        self.assertEqual(typing.get_type_hints(Client.testI64), {'return': int, 'thing': int})

    def test_double(self):
        self.assertEqual(typing.get_type_hints(Client.testDouble), {'return': float, 'thing': float})

    def test_binary(self):
        self.assertEqual(typing.get_type_hints(Client.testBinary), {'return': bytes, 'thing': bytes})

    def test_struct(self):
        self.assertEqual(typing.get_type_hints(Client.testStruct), {'return': Xtruct, 'thing': Xtruct})

    def test_map(self):
        self.assertEqual(typing.get_type_hints(Client.testMap), {'return': dict[int, int], 'thing': dict[int, int]})
    
    def test_list(self):
        self.assertEqual(typing.get_type_hints(Client.testList), {'return': list[int], 'thing': list[int]})

    def test_set(self):
        self.assertEqual(typing.get_type_hints(Client.testSet), {'return': set[int], 'thing': set[int]})

    def test_complex_service(self):
        self.assertEqual(
            typing.get_type_hints(TypeHintsClient.ping),
            {
                'return': Container,
                'data': dict[str, list[Inner]],
                'payload': Payload | None,
            },
        )
        self.assertEqual(
            typing.get_type_hints(TypeHintsClient.batch),
            {
                'return': list[Container],
                'items': list[Container],
            },
        )

    def test_complex_struct_init(self):
        hints = typing.get_type_hints(Container.__init__)
        self.assertEqual(hints['inner_map'], dict[str, list[Inner]] | None)
        self.assertEqual(hints['uuid_sets'], list[set[UUID]] | None)
        self.assertEqual(hints['payload'], Payload | None)
        self.assertEqual(hints['nested_numbers'], list[dict[str, list[int]]] | None)
        self.assertEqual(hints['status'], Status | None)
