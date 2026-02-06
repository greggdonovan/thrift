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
#
AUTOMAKE_OPTIONS = serial-tests

py_unit_tests = RunClientServer.py

thrift_gen =                                    \
        gen-py/ThriftTest/__init__.py           \
        gen-py/DebugProtoTest/__init__.py \
        gen-py/DoubleConstantsTest/__init__.py \
        gen-py/Recursive/__init__.py \
        gen-py-default/ThriftTest/__init__.py           \
        gen-py-default/DebugProtoTest/__init__.py \
        gen-py-default/DoubleConstantsTest/__init__.py \
        gen-py-default/Recursive/__init__.py \
        gen-py-slots/ThriftTest/__init__.py           \
        gen-py-slots/DebugProtoTest/__init__.py \
        gen-py-slots/DoubleConstantsTest/__init__.py \
        gen-py-slots/Recursive/__init__.py \
        gen-py-dynamic/ThriftTest/__init__.py           \
        gen-py-dynamic/DebugProtoTest/__init__.py \
        gen-py-dynamic/DoubleConstantsTest/__init__.py \
        gen-py-dynamic/Recursive/__init__.py \
        gen-py-dynamicslots/ThriftTest/__init__.py           \
        gen-py-dynamicslots/DebugProtoTest/__init__.py \
        gen-py-dynamicslots/DoubleConstantsTest/__init__.py \
        gen-py-dynamicslots/Recursive/__init__.py

distdir:
	$(MAKE) $(AM_MAKEFLAGS) distdir-am

precross: $(thrift_gen)
BUILT_SOURCES = $(thrift_gen)

helper_scripts=                                 \
        TestClient.py                           \
        TestServer.py

check_SCRIPTS=                                  \
        $(thrift_gen) \
        $(py_unit_tests)                        \
        $(helper_scripts)

TESTS= $(py_unit_tests)


gen-py/%/__init__.py: ../%.thrift $(THRIFT)
	test -f ../v0.16/$(notdir $<) \
	&& $(THRIFT) --gen py  ../v0.16/$(notdir $<) \
	|| $(THRIFT) --gen py  $<

gen-py-default/%/__init__.py: ../%.thrift $(THRIFT)
	test -d gen-py-default || $(MKDIR_P) gen-py-default
	test -f ../v0.16/$(notdir $<) \
	&& $(THRIFT) --gen py -out gen-py-default ../v0.16/$(notdir $<) \
	|| $(THRIFT) --gen py -out gen-py-default $<

gen-py-slots/%/__init__.py: ../%.thrift $(THRIFT)
	test -d gen-py-slots || $(MKDIR_P) gen-py-slots
	test ../v0.16/$(notdir $<) \
	&& $(THRIFT) --gen py:slots -out gen-py-slots ../v0.16/$(notdir $<) \
	|| $(THRIFT) --gen py:slots -out gen-py-slots $<

gen-py-dynamic/%/__init__.py: ../%.thrift $(THRIFT)
	test -d gen-py-dynamic || $(MKDIR_P) gen-py-dynamic
	test ../v0.16/$(notdir $<) \
	&& $(THRIFT) --gen py:dynamic -out gen-py-dynamic ../v0.16/$(notdir $<) \
	|| $(THRIFT) --gen py:dynamic -out gen-py-dynamic $<

gen-py-dynamicslots/%/__init__.py: ../%.thrift $(THRIFT)
	test -d gen-py-dynamicslots || $(MKDIR_P) gen-py-dynamicslots
	test ../v0.16/$(notdir $<) \
	&& $(THRIFT) --gen py:dynamic,slots -out gen-py-dynamicslots ../v0.16/$(notdir $<) \
	|| $(THRIFT) --gen py:dynamic,slots -out gen-py-dynamicslots $<

clean-local:
	$(RM) -r build
	find . -type f \( -iname "*.pyc" \) | xargs rm -f
	find . -type d \( -iname "__pycache__" -or -iname "_trial_temp" \) | xargs rm -rf
	$(RM) -r gen-py*/

dist-hook:
	find $(distdir) -type f \( -iname "*.pyc" \) | xargs rm -f
	find $(distdir) -type d \( -iname "__pycache__" -or -iname "_trial_temp" \) | xargs rm -rf
	$(RM) -r $(distdir)/gen-py*/
