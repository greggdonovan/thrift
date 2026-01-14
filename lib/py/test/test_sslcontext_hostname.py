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

import os
import socket
import ssl
import unittest

import _import_local_thrift  # noqa

from thrift.transport.TSSLSocket import TSSLSocket

SCRIPT_DIR = os.path.realpath(os.path.dirname(__file__))
ROOT_DIR = os.path.dirname(os.path.dirname(os.path.dirname(SCRIPT_DIR)))
CA_CERT = os.path.join(ROOT_DIR, 'test', 'keys', 'CA.pem')


class TSSLSocketHostnameVerificationTest(unittest.TestCase):
    def _wrap_client(self, **kwargs):
        client = TSSLSocket('localhost', 0, **kwargs)
        sock = socket.socket()
        ssl_sock = None
        try:
            ssl_sock = client._wrap_socket(sock)
        finally:
            if ssl_sock is not None:
                ssl_sock.close()
            else:
                sock.close()
        return client

    def test_check_hostname_enabled_with_verification(self):
        client = self._wrap_client(
            cert_reqs=ssl.CERT_REQUIRED,
            ca_certs=CA_CERT,
            server_hostname='localhost',
        )
        self.assertTrue(getattr(client.ssl_context, 'check_hostname', False))
