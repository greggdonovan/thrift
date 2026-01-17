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
import ssl
import unittest
import warnings

import _import_local_thrift  # noqa
from thrift.protocol import TBinaryProtocol
from thrift.server import THttpServer as THttpServerModule
from thrift.transport import THttpClient, TTransport

SCRIPT_DIR = os.path.realpath(os.path.dirname(__file__))
ROOT_DIR = os.path.dirname(os.path.dirname(os.path.dirname(SCRIPT_DIR)))
SERVER_CERT = os.path.join(ROOT_DIR, 'test', 'keys', 'server.crt')
SERVER_KEY = os.path.join(ROOT_DIR, 'test', 'keys', 'server.key')


class TestTFileObjectTransport(unittest.TestCase):

    def test_TFileObjectTransport(self):
        test_dir = os.path.dirname(os.path.abspath(__file__))
        datatxt_path = os.path.join(test_dir, 'data.txt')
        buffer = '{"soft":"thrift","version":0.13,"1":true}'
        with open(datatxt_path, "w+") as f:
            buf = TTransport.TFileObjectTransport(f)
            buf.write(buffer)
            buf.flush()
            buf.close()

        with open(datatxt_path, "rb") as f:
            buf = TTransport.TFileObjectTransport(f)
            value = buf.read(len(buffer)).decode('utf-8')
            self.assertEqual(buffer, value)
            buf.close()
        os.remove(datatxt_path)


class TestMemoryBuffer(unittest.TestCase):

    def test_memorybuffer_write(self):
        data = '{"1":[1,"hello"],"a":{"A":"abc"},"bool":true,"num":12345}'

        buffer_w = TTransport.TMemoryBuffer()
        buffer_w.write(data.encode('utf-8'))
        value = buffer_w.getvalue()
        self.assertEqual(value.decode('utf-8'), data)
        buffer_w.close()

    def test_memorybuffer_read(self):
        data = '{"1":[1, "hello"],"a":{"A":"abc"},"bool":true,"num":12345}'

        buffer_r = TTransport.TMemoryBuffer(data.encode('utf-8'))
        value_r = buffer_r.read(len(data))
        value = buffer_r.getvalue()
        self.assertEqual(value.decode('utf-8'), data)
        self.assertEqual(value_r.decode('utf-8'), data)
        buffer_r.close()


class TestHttpTls(unittest.TestCase):
    def test_http_client_minimum_tls(self):
        if not hasattr(ssl, 'TLSVersion'):
            self.skipTest('TLSVersion is not available')
        client = THttpClient.THttpClient('https://localhost:8443/')
        self.assertGreaterEqual(client.context.minimum_version, ssl.TLSVersion.TLSv1_2)
        if client.context.maximum_version != ssl.TLSVersion.MAXIMUM_SUPPORTED:
            self.assertGreaterEqual(client.context.maximum_version, ssl.TLSVersion.TLSv1_2)

    def test_http_client_rejects_legacy_context(self):
        if not hasattr(ssl, 'TLSVersion'):
            self.skipTest('TLSVersion is not available')
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
        with warnings.catch_warnings():
            warnings.filterwarnings('ignore', category=DeprecationWarning)
            context.minimum_version = ssl.TLSVersion.TLSv1_1
        with self.assertRaises(ValueError):
            THttpClient.THttpClient('https://localhost:8443/', ssl_context=context)

    def test_http_server_minimum_tls(self):
        if not hasattr(ssl, 'TLSVersion'):
            self.skipTest('TLSVersion is not available')

        class DummyProcessor(object):
            def on_message_begin(self, _on_begin):
                return None

            def process(self, _iprot, _oprot):
                return None

        server = THttpServerModule.THttpServer(
            DummyProcessor(),
            ('localhost', 0),
            TBinaryProtocol.TBinaryProtocolFactory(),
            cert_file=SERVER_CERT,
            key_file=SERVER_KEY,
        )
        try:
            context = server.httpd.socket.context
            self.assertGreaterEqual(context.minimum_version, ssl.TLSVersion.TLSv1_2)
            if context.maximum_version != ssl.TLSVersion.MAXIMUM_SUPPORTED:
                self.assertGreaterEqual(context.maximum_version, ssl.TLSVersion.TLSv1_2)
        finally:
            server.shutdown()


if __name__ == '__main__':
    unittest.main()
