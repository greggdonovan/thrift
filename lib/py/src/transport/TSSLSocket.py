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

import logging
import os
import socket
import ssl
import warnings

from .sslcompat import (
    validate_minimum_tls,
    MINIMUM_TLS_VERSION,
)
from thrift.transport import TSocket
from thrift.transport.TTransport import TTransportException

logger = logging.getLogger(__name__)
warnings.filterwarnings(
    'default', category=DeprecationWarning, module=__name__)


class TSSLBase:
    _minimum_tls_version = MINIMUM_TLS_VERSION

    def _init_context(self, ssl_version):
        """Initialize SSL context with the given version.

        Args:
            ssl_version: Minimum TLS version to accept. Must be
                        ssl.TLSVersion.TLSv1_2 or ssl.TLSVersion.TLSv1_3.
                        Higher versions are negotiated when available.
                        Deprecated protocol constants are not supported.
        """
        if not isinstance(ssl_version, ssl.TLSVersion):
            raise ValueError(
                'ssl_version must be ssl.TLSVersion.TLSv1_2 or ssl.TLSVersion.TLSv1_3. '
                'Deprecated protocol constants (PROTOCOL_*) are not supported.'
            )
        if ssl_version < self._minimum_tls_version:
            raise ValueError(
                'TLS 1.0/1.1 are not supported; use ssl.TLSVersion.TLSv1_2 or higher.'
            )

        if self._server_side:
            protocol = ssl.PROTOCOL_TLS_SERVER
        else:
            protocol = ssl.PROTOCOL_TLS_CLIENT
        self._context = ssl.SSLContext(protocol)
        self._context.minimum_version = ssl_version
        # Don't set maximum_version - allow negotiation up to newest TLS

    @property
    def _should_verify(self):
        if self._custom_context:
            return self._context.verify_mode != ssl.CERT_NONE
        return self.cert_reqs != ssl.CERT_NONE

    @property
    def ssl_version(self):
        return self.ssl_context.protocol

    @property
    def ssl_context(self):
        return self._context

    def _deprecated_arg(self, args, kwargs, pos, key):
        if len(args) <= pos:
            return
        real_pos = pos + 3
        warnings.warn(
            '%dth positional argument is deprecated.'
            'please use keyword argument instead.'
            % real_pos, DeprecationWarning, stacklevel=3)

        if key in kwargs:
            raise TypeError(
                'Duplicate argument: %dth argument and %s keyword argument.'
                % (real_pos, key))
        kwargs[key] = args[pos]

    def _unix_socket_arg(self, host, port, args, kwargs):
        key = 'unix_socket'
        if host is None and port is None and len(args) == 1 and key not in kwargs:
            kwargs[key] = args[0]
            return True
        return False

    def __init__(self, server_side, host, ssl_opts):
        self._server_side = server_side
        self._context = ssl_opts.pop('ssl_context', None)
        self._server_hostname = None
        if not self._server_side:
            self._server_hostname = ssl_opts.pop('server_hostname', host)
        if self._context:
            self._custom_context = True
            validate_minimum_tls(self._context)
            if ssl_opts:
                raise ValueError(
                    'Incompatible arguments: ssl_context and %s'
                    % ' '.join(ssl_opts.keys()))
        else:
            self._custom_context = False
            ssl_version = ssl_opts.pop('ssl_version', self._minimum_tls_version)
            self._init_context(ssl_version)
            self.cert_reqs = ssl_opts.pop('cert_reqs', ssl.CERT_REQUIRED)
            self.ca_certs = ssl_opts.pop('ca_certs', None)
            self.keyfile = ssl_opts.pop('keyfile', None)
            self.certfile = ssl_opts.pop('certfile', None)
            self.ciphers = ssl_opts.pop('ciphers', None)

            if ssl_opts:
                raise ValueError(
                    'Unknown keyword arguments: ', ' '.join(ssl_opts.keys()))

            if self._should_verify:
                if not self.ca_certs:
                    raise ValueError(
                        'ca_certs is needed when cert_reqs is not ssl.CERT_NONE')
                if not os.access(self.ca_certs, os.R_OK):
                    raise IOError('Certificate Authority ca_certs file "%s" '
                                  'is not readable, cannot validate SSL '
                                  'certificates.' % (self.ca_certs))

    @property
    def certfile(self):
        return self._certfile

    @certfile.setter
    def certfile(self, certfile):
        if self._server_side and not certfile:
            raise ValueError('certfile is needed for server-side')
        if certfile and not os.access(certfile, os.R_OK):
            raise IOError('No such certfile found: %s' % (certfile))
        self._certfile = certfile

    def _wrap_socket(self, sock):
        if not self._custom_context:
            if self._server_side:
                # Server contexts never perform hostname checks.
                self.ssl_context.check_hostname = False
            else:
                # For client sockets, use OpenSSL hostname checking when we
                # require a verified server certificate. OpenSSL handles
                # hostname validation during the TLS handshake.
                self.ssl_context.check_hostname = (
                    self.cert_reqs in (ssl.CERT_REQUIRED, ssl.CERT_OPTIONAL) and
                    bool(self._server_hostname)
                )
            self.ssl_context.verify_mode = self.cert_reqs
            if self.certfile:
                self.ssl_context.load_cert_chain(self.certfile, self.keyfile)
            if self.ciphers:
                self.ssl_context.set_ciphers(self.ciphers)
            if self.ca_certs:
                self.ssl_context.load_verify_locations(self.ca_certs)
        return self.ssl_context.wrap_socket(
            sock, server_side=self._server_side,
            server_hostname=self._server_hostname)


class TSSLSocket(TSocket.TSocket, TSSLBase):
    """
    SSL implementation of TSocket

    This class creates outbound sockets wrapped using the
    python standard ssl module for encrypted connections.
    """

    # New signature
    # def __init__(self, host='localhost', port=9090, unix_socket=None,
    #              **ssl_args):
    # Deprecated signature
    # def __init__(self, host='localhost', port=9090, validate=True,
    #              ca_certs=None, keyfile=None, certfile=None,
    #              unix_socket=None, ciphers=None):
    def __init__(self, host='localhost', port=9090, *args, **kwargs):
        """Positional arguments: ``host``, ``port``, ``unix_socket``

        Keyword arguments: ``keyfile``, ``certfile``, ``cert_reqs``,
                           ``ssl_version`` (minimum TLS version, defaults to 1.2),
                           ``ca_certs``, ``ciphers``, ``server_hostname``
        Passed to ssl.wrap_socket. See ssl.wrap_socket documentation.

        Alternative keyword arguments:
          ``ssl_context``: ssl.SSLContext to be used for SSLContext.wrap_socket
          ``server_hostname``: Passed to SSLContext.wrap_socket

        Common keyword argument:
          ``socket_keepalive`` enable TCP keepalive, default off.

        Note: Hostname verification is handled by OpenSSL during the TLS
        handshake when cert_reqs=ssl.CERT_REQUIRED and server_hostname is set.
        """
        self.peercert = None

        if args:
            if len(args) > 6:
                raise TypeError('Too many positional argument')
            if not self._unix_socket_arg(host, port, args, kwargs):
                self._deprecated_arg(args, kwargs, 0, 'validate')
            self._deprecated_arg(args, kwargs, 1, 'ca_certs')
            self._deprecated_arg(args, kwargs, 2, 'keyfile')
            self._deprecated_arg(args, kwargs, 3, 'certfile')
            self._deprecated_arg(args, kwargs, 4, 'unix_socket')
            self._deprecated_arg(args, kwargs, 5, 'ciphers')

        validate = kwargs.pop('validate', None)
        if validate is not None:
            cert_reqs_name = 'CERT_REQUIRED' if validate else 'CERT_NONE'
            warnings.warn(
                'validate is deprecated. please use cert_reqs=ssl.%s instead'
                % cert_reqs_name,
                DeprecationWarning, stacklevel=2)
            if 'cert_reqs' in kwargs:
                raise TypeError('Cannot specify both validate and cert_reqs')
            kwargs['cert_reqs'] = ssl.CERT_REQUIRED if validate else ssl.CERT_NONE

        unix_socket = kwargs.pop('unix_socket', None)
        socket_keepalive = kwargs.pop('socket_keepalive', False)
        TSSLBase.__init__(self, False, host, kwargs)
        TSocket.TSocket.__init__(self, host, port, unix_socket,
                                 socket_keepalive=socket_keepalive)

    def close(self):
        if not self.handle:
            return
        try:
            self.handle.settimeout(0.001)
            self.handle = self.handle.unwrap()
        except (ssl.SSLError, socket.error, OSError):
            # could not complete shutdown in a reasonable amount of time.  bail.
            pass
        TSocket.TSocket.close(self)

    @property
    def validate(self):
        warnings.warn('validate is deprecated. please use cert_reqs instead',
                      DeprecationWarning, stacklevel=2)
        return self.cert_reqs != ssl.CERT_NONE

    @validate.setter
    def validate(self, value):
        warnings.warn('validate is deprecated. please use cert_reqs instead',
                      DeprecationWarning, stacklevel=2)
        self.cert_reqs = ssl.CERT_REQUIRED if value else ssl.CERT_NONE

    def _do_open(self, family, socktype):
        plain_sock = socket.socket(family, socktype)
        try:
            return self._wrap_socket(plain_sock)
        except Exception as ex:
            plain_sock.close()
            msg = 'failed to initialize SSL'
            logger.exception(msg)
            raise TTransportException(type=TTransportException.NOT_OPEN, message=msg, inner=ex)

    def open(self):
        super(TSSLSocket, self).open()
        # Hostname verification is handled by OpenSSL during the TLS handshake
        # when check_hostname=True is set on the SSLContext.
        if self._should_verify:
            self.peercert = self.handle.getpeercert()


class TSSLServerSocket(TSocket.TServerSocket, TSSLBase):
    """SSL implementation of TServerSocket

    This uses the ssl module's wrap_socket() method to provide SSL
    negotiated encryption.
    """

    # New signature
    # def __init__(self, host='localhost', port=9090, unix_socket=None, **ssl_args):
    # Deprecated signature
    # def __init__(self, host=None, port=9090, certfile='cert.pem', unix_socket=None, ciphers=None):
    def __init__(self, host=None, port=9090, *args, **kwargs):
        """Positional arguments: ``host``, ``port``, ``unix_socket``

        Keyword arguments: ``keyfile``, ``certfile``, ``cert_reqs``,
                           ``ssl_version`` (minimum TLS version, defaults to 1.2),
                           ``ca_certs``, ``ciphers``
        See ssl.wrap_socket documentation.

        Alternative keyword arguments:
          ``ssl_context``: ssl.SSLContext to be used for SSLContext.wrap_socket

        For mTLS (mutual TLS), set cert_reqs=ssl.CERT_REQUIRED and provide
        ca_certs to verify client certificates. Client certificate validation
        checks that the certificate is signed by a trusted CA.
        """
        if args:
            if len(args) > 3:
                raise TypeError('Too many positional argument')
            if not self._unix_socket_arg(host, port, args, kwargs):
                self._deprecated_arg(args, kwargs, 0, 'certfile')
            self._deprecated_arg(args, kwargs, 1, 'unix_socket')
            self._deprecated_arg(args, kwargs, 2, 'ciphers')

        if 'ssl_context' not in kwargs:
            # Preserve existing behaviors for default values
            if 'cert_reqs' not in kwargs:
                kwargs['cert_reqs'] = ssl.CERT_NONE
            if 'certfile' not in kwargs:
                kwargs['certfile'] = 'cert.pem'

        unix_socket = kwargs.pop('unix_socket', None)
        TSSLBase.__init__(self, True, None, kwargs)
        TSocket.TServerSocket.__init__(self, host, port, unix_socket)

    def setCertfile(self, certfile):
        """Set or change the server certificate file used to wrap new
        connections.

        @param certfile: The filename of the server certificate,
                         i.e. '/etc/certs/server.pem'
        @type certfile: str

        Raises an IOError exception if the certfile is not present or unreadable.
        """
        warnings.warn(
            'setCertfile is deprecated. please use certfile property instead.',
            DeprecationWarning, stacklevel=2)
        self.certfile = certfile

    def accept(self):
        plain_client, addr = self.handle.accept()
        try:
            client = self._wrap_socket(plain_client)
        except (ssl.SSLError, socket.error, OSError):
            logger.exception('Error while accepting from %s', addr)
            # failed handshake/ssl wrap, close socket to client
            plain_client.close()
            # raise
            # We can't raise the exception, because it kills most TServer derived
            # serve() methods.
            # Instead, return None, and let the TServer instance deal with it in
            # other exception handling.  (but TSimpleServer dies anyway)
            return None

        # For mTLS, OpenSSL validates that the client certificate is signed
        # by a trusted CA during the handshake (when cert_reqs=CERT_REQUIRED).
        result = TSocket.TSocket()
        result.handle = client
        return result
