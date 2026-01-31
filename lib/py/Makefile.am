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
DESTDIR ?= /

py-build:
	$(PYTHON) setup.py build
py-test: py-build
	$(PYTHON) test/thrift_json.py
	$(PYTHON) test/thrift_transport.py
	$(PYTHON) test/test_sslcontext_hostname.py
	$(PYTHON) test/test_sslsocket.py
	$(PYTHON) test/test_socket.py
	$(PYTHON) test/thrift_TBinaryProtocol.py
	$(PYTHON) test/thrift_TZlibTransport.py
	$(PYTHON) test/thrift_TCompactProtocol.py
	$(PYTHON) test/thrift_TNonblockingServer.py
	$(PYTHON) test/thrift_TSerializer.py
	$(PYTHON) test/test_type_check.py
py-security:
	@command -v uv >/dev/null 2>&1 || { \
		echo "Installing uv..."; \
		curl -LsSf https://astral.sh/uv/install.sh | sh; \
	}
	uv tool run bandit -r src --severity-level medium --confidence-level medium
	uv tool run semgrep scan --config p/security-audit --config p/python \
		--severity WARNING --severity ERROR --metrics off src test

all-local: py-build
	${THRIFT} --gen py test/test_thrift_file/TestServer.thrift
	${THRIFT} --gen py ../../test/v0.16/FuzzTestNoUuid.thrift

# We're ignoring prefix here because site-packages seems to be
# the equivalent of /usr/local/lib in Python land.
# Old version (can't put inline because it's not portable).
#$(PYTHON) setup.py install --prefix=$(prefix) --root=$(DESTDIR) $(PYTHON_SETUPUTIL_ARGS)
install-exec-hook:
	$(PYTHON) -m pip install . --root=$(DESTDIR) --prefix=$(PY_PREFIX) $(PYTHON_SETUPUTIL_ARGS)

check-local: all py-test py-security


clean-local:
	$(RM) -r build
	$(RM) -r gen-py
	find . -type f \( -iname "*.pyc" \) | xargs rm -f
	find . -type d \( -iname "__pycache__" -or -iname "_trial_temp" \) | xargs rm -rf

dist-hook:
	find $(distdir) -type f \( -iname "*.pyc" \) | xargs rm -f
	find $(distdir) -type d \( -iname "__pycache__" -or -iname "_trial_temp" \) | xargs rm -rf

distdir:
	$(MAKE) $(AM_MAKEFLAGS) distdir-am

EXTRA_DIST = \
	CMakeLists.txt \
	MANIFEST.in \
	coding_standards.md \
	compat \
	setup.py \
	setup.cfg \
	src \
	test \
	README.md
