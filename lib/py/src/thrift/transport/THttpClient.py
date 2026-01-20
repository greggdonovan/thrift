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

from __future__ import annotations

from typing import Any, Mapping

from io import BytesIO
import os
import ssl
import sys
import warnings
import base64

import urllib.parse
import urllib.request
import http.client

from .TTransport import ReadableBuffer, TTransportBase


def _enforce_minimum_tls(context: ssl.SSLContext) -> None:
    if not hasattr(ssl, 'TLSVersion'):
        return
    minimum = ssl.TLSVersion.TLSv1_2
    if hasattr(context, 'minimum_version'):
        if context.minimum_version < minimum:
            context.minimum_version = minimum
    if hasattr(context, 'maximum_version'):
        if (context.maximum_version != ssl.TLSVersion.MAXIMUM_SUPPORTED and
                context.maximum_version < minimum):
            raise ValueError('TLS maximum_version must be TLS 1.2 or higher.')


def _validate_minimum_tls(context: ssl.SSLContext) -> None:
    if not hasattr(ssl, 'TLSVersion'):
        return
    minimum = ssl.TLSVersion.TLSv1_2
    if hasattr(context, 'minimum_version'):
        if context.minimum_version < minimum:
            raise ValueError('ssl_context.minimum_version must be TLS 1.2 or higher.')
    if hasattr(context, 'maximum_version'):
        if (context.maximum_version != ssl.TLSVersion.MAXIMUM_SUPPORTED and
                context.maximum_version < minimum):
            raise ValueError('ssl_context.maximum_version must be TLS 1.2 or higher.')


class THttpClient(TTransportBase):
    """Http implementation of TTransport base."""
    host: str
    port: int
    path: str
    scheme: str
    realhost: str | None
    realport: int | None
    proxy_auth: str | None

    def __init__(self, uri_or_host: str, port: int | None = None, path: str | None = None,
                 cafile: str | None = None, cert_file: str | None = None, key_file: str | None = None,
                 ssl_context: ssl.SSLContext | None = None) -> None:
        """THttpClient supports two different types of construction:

        THttpClient(host, port, path) - deprecated
        THttpClient(uri, [port=<n>, path=<s>, cafile=<filename>, cert_file=<filename>, key_file=<filename>, ssl_context=<context>])

        Only the second supports https.  To properly authenticate against the server,
        provide the client's identity by specifying cert_file and key_file.  To properly
        authenticate the server, specify either cafile or ssl_context with a CA defined.
        NOTE: if ssl_context is defined, it will override any provided cert_file, key_file, and cafile.
        """
        if port is not None:
            warnings.warn(
                "Please use the THttpClient('http{s}://host:port/path') constructor",
                DeprecationWarning,
                stacklevel=2)
            self.host = uri_or_host
            self.port = port
            assert path
            self.path = path
            self.scheme = 'http'
        else:
            parsed = urllib.parse.urlparse(uri_or_host)
            self.scheme = parsed.scheme
            assert self.scheme in ('http', 'https')
            if self.scheme == 'http':
                self.port = parsed.port or http.client.HTTP_PORT
            elif self.scheme == 'https':
                self.port = parsed.port or http.client.HTTPS_PORT
                if ssl_context is not None:
                    _validate_minimum_tls(ssl_context)
                    self.context = ssl_context
                else:
                    self.context = ssl.create_default_context(cafile=cafile)
                    if cert_file:
                        self.context.load_cert_chain(certfile=cert_file, keyfile=key_file)
                    elif key_file:
                        raise ValueError("key_file requires cert_file")
                    _enforce_minimum_tls(self.context)
            host = parsed.hostname
            if host is None:
                raise ValueError("URL must include a hostname")
            self.host = host
            self.path = parsed.path
            if parsed.query:
                self.path += '?%s' % parsed.query
        try:
            proxy = urllib.request.getproxies()[self.scheme]
        except KeyError:
            proxy = None
        else:
            if urllib.request.proxy_bypass(self.host):
                proxy = None
        if proxy:
            parsed = urllib.parse.urlparse(proxy)
            if self.host is None:
                raise ValueError("Proxy configuration requires a hostname")
            self.realhost = self.host
            self.realport = self.port
            proxy_host = parsed.hostname
            if proxy_host is None:
                raise ValueError("Proxy URL must include a hostname")
            self.host = proxy_host
            self.port = parsed.port or http.client.HTTP_PORT
            self.proxy_auth = self.basic_proxy_auth_header(parsed)
        else:
            self.realhost = self.realport = self.proxy_auth = None
        self.__wbuf = BytesIO()
        self.__http: http.client.HTTPConnection | None = None
        self.__http_response: http.client.HTTPResponse | None = None
        self.__timeout = None
        self.__custom_headers = None
        self.headers = None

    @staticmethod
    def basic_proxy_auth_header(proxy: Any) -> str | None:
        if proxy is None or not proxy.username:
            return None
        ap = "%s:%s" % (urllib.parse.unquote(proxy.username),
                        urllib.parse.unquote(proxy.password))
        cr = base64.b64encode(ap.encode()).strip()
        return "Basic " + cr.decode("ascii")

    def using_proxy(self) -> bool:
        return self.realhost is not None

    def open(self) -> None:
        if self.scheme == 'http':
            self.__http = http.client.HTTPConnection(self.host, self.port,
                                                     timeout=self.__timeout)
        elif self.scheme == 'https':
            # Python 3.10+ uses an explicit SSLContext; TLS 1.2+ enforced in __init__.
            self.__http = http.client.HTTPSConnection(  # nosem
                self.host, self.port,
                timeout=self.__timeout,
                context=self.context)
        if self.using_proxy() and self.__http is not None:
            assert self.realhost is not None
            headers = None
            if self.proxy_auth is not None:
                headers = {"Proxy-Authorization": self.proxy_auth}
            self.__http.set_tunnel(self.realhost, self.realport, headers)

    def close(self) -> None:
        if self.__http is not None:
            self.__http.close()
        self.__http = None
        self.__http_response = None

    def isOpen(self) -> bool:
        return self.__http is not None

    def setTimeout(self, ms: float | None) -> None:
        if ms is None:
            self.__timeout = None
        else:
            self.__timeout = ms / 1000.0

    def setCustomHeaders(self, headers: Mapping[str, str] | None) -> None:
        self.__custom_headers = headers

    def read(self, sz: int) -> bytes:
        if self.__http_response is None:
            raise EOFError("HTTP response not available")
        return self.__http_response.read(sz)

    def write(self, buf: ReadableBuffer) -> None:
        self.__wbuf.write(buf)

    def flush(self) -> None:
        if self.isOpen():
            self.close()
        self.open()
        assert self.__http is not None

        # Pull data out of buffer
        data = self.__wbuf.getvalue()
        self.__wbuf = BytesIO()

        # HTTP request
        if self.using_proxy() and self.scheme == "http":
            # need full URL of real host for HTTP proxy here (HTTPS uses CONNECT tunnel)
            self.__http.putrequest('POST', "http://%s:%s%s" %
                                   (self.realhost, self.realport, self.path))
        else:
            self.__http.putrequest('POST', self.path)

        # Write headers
        self.__http.putheader('Content-Type', 'application/x-thrift')
        self.__http.putheader('Content-Length', str(len(data)))
        if self.using_proxy() and self.scheme == "http" and self.proxy_auth is not None:
            self.__http.putheader("Proxy-Authorization", self.proxy_auth)

        if not self.__custom_headers or 'User-Agent' not in self.__custom_headers:
            user_agent = 'Python/THttpClient'
            script = os.path.basename(sys.argv[0])
            if script:
                user_agent = '%s (%s)' % (user_agent, urllib.parse.quote(script))
            self.__http.putheader('User-Agent', user_agent)

        if self.__custom_headers:
            for key, val in self.__custom_headers.items():
                self.__http.putheader(key, val)

        # Saves the cookie sent by the server in the previous response.
        # HTTPConnection.putheader can only be called after a request has been
        # started, and before it's been sent.
        if self.headers and 'Set-Cookie' in self.headers:
            self.__http.putheader('Cookie', self.headers['Set-Cookie'])

        self.__http.endheaders()

        # Write payload
        self.__http.send(data)

        # Get reply to flush the request
        self.__http_response = self.__http.getresponse()
        self.code = self.__http_response.status
        self.message = self.__http_response.reason
        self.headers = self.__http_response.msg
