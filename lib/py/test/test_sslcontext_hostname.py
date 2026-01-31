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

"""Tests for SSL hostname verification via OpenSSL.

For Python 3.10+, hostname verification is handled by OpenSSL during the
TLS handshake when SSLContext.check_hostname is True.
"""

import os
import socket
import ssl
import unittest
import warnings

import _import_local_thrift  # noqa

from thrift.transport.TSSLSocket import TSSLSocket
from thrift.transport import sslcompat

SCRIPT_DIR = os.path.realpath(os.path.dirname(__file__))
ROOT_DIR = os.path.dirname(os.path.dirname(os.path.dirname(SCRIPT_DIR)))
CA_CERT = os.path.join(ROOT_DIR, 'test', 'keys', 'CA.pem')


class TSSLSocketHostnameVerificationTest(unittest.TestCase):
    """Tests that OpenSSL hostname verification is properly configured."""

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
        """check_hostname should be True when CERT_REQUIRED and server_hostname set."""
        client = self._wrap_client(
            cert_reqs=ssl.CERT_REQUIRED,
            ca_certs=CA_CERT,
            server_hostname='localhost',
        )
        self.assertTrue(client.ssl_context.check_hostname)

    def test_check_hostname_disabled_without_server_hostname(self):
        """check_hostname should be False when no server_hostname."""
        client = self._wrap_client(
            cert_reqs=ssl.CERT_REQUIRED,
            ca_certs=CA_CERT,
            server_hostname=None,
        )
        self.assertFalse(client.ssl_context.check_hostname)

    def test_check_hostname_disabled_with_cert_none(self):
        """check_hostname should be False when CERT_NONE."""
        client = self._wrap_client(
            cert_reqs=ssl.CERT_NONE,
            server_hostname='localhost',
        )
        self.assertFalse(client.ssl_context.check_hostname)


class TLSVersionEnforcementTest(unittest.TestCase):
    """Tests for TLS version enforcement utilities."""

    def test_enforce_minimum_tls_upgrades_version(self):
        """enforce_minimum_tls should set minimum_version to TLS 1.2."""
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
        with warnings.catch_warnings():
            warnings.filterwarnings('ignore', category=DeprecationWarning)
            context.minimum_version = ssl.TLSVersion.TLSv1
        sslcompat.enforce_minimum_tls(context)
        self.assertEqual(context.minimum_version, ssl.TLSVersion.TLSv1_2)

    def test_enforce_minimum_tls_rejects_low_maximum(self):
        """enforce_minimum_tls should reject maximum_version below TLS 1.2."""
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
        with warnings.catch_warnings():
            warnings.filterwarnings('ignore', category=DeprecationWarning)
            context.maximum_version = ssl.TLSVersion.TLSv1_1
        with self.assertRaises(ValueError):
            sslcompat.enforce_minimum_tls(context)

    def test_validate_minimum_tls_rejects_low_minimum(self):
        """validate_minimum_tls should reject minimum_version below TLS 1.2."""
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE
        with warnings.catch_warnings():
            warnings.filterwarnings('ignore', category=DeprecationWarning)
            context.minimum_version = ssl.TLSVersion.TLSv1
        with self.assertRaises(ValueError):
            sslcompat.validate_minimum_tls(context)

    def test_validate_minimum_tls_accepts_tls12(self):
        """validate_minimum_tls should accept TLS 1.2."""
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE
        context.minimum_version = ssl.TLSVersion.TLSv1_2
        # Should not raise
        sslcompat.validate_minimum_tls(context)

    def test_validate_minimum_tls_accepts_tls13(self):
        """validate_minimum_tls should accept TLS 1.3."""
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE
        context.minimum_version = ssl.TLSVersion.TLSv1_3
        # Should not raise
        sslcompat.validate_minimum_tls(context)


if __name__ == '__main__':
    unittest.main()
