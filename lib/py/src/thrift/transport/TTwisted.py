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

from typing import Any, Callable, Protocol, TYPE_CHECKING, cast

from io import BytesIO
import struct

from zope.interface import implementer
from twisted.internet.protocol import ServerFactory, ClientFactory, \
    connectionDone
from twisted.internet import defer
from twisted.internet.threads import deferToThread
from twisted.protocols import basic
from twisted.web import server, resource, http

from thrift.transport import TTransport
from thrift.transport.TTransport import ReadableBuffer

if TYPE_CHECKING:
    class _InterfaceBase(Protocol):
        pass

    def Attribute(_: str) -> Any:
        ...
else:
    from zope.interface import Interface as _InterfaceBase, Attribute


class _ServerFactory(Protocol):
    processor: Any
    iprot_factory: Any
    oprot_factory: Any


class _ClientFactory(Protocol):
    client_class: Any
    iprot_factory: Any
    oprot_factory: Any


class _TwistedTransport(Protocol):
    def write(self, data: bytes) -> None:
        ...

    def loseConnection(self) -> None:
        ...


class TMessageSenderTransport(TTransport.TTransportBase):

    def __init__(self) -> None:
        self.__wbuf = BytesIO()

    def write(self, buf: ReadableBuffer) -> None:
        self.__wbuf.write(buf)

    def flush(self) -> Any:
        msg = self.__wbuf.getvalue()
        self.__wbuf = BytesIO()
        return self.sendMessage(msg)

    def sendMessage(self, message: bytes) -> Any:
        raise NotImplementedError


class TCallbackTransport(TMessageSenderTransport):

    def __init__(self, func: Callable[[bytes], Any]) -> None:
        TMessageSenderTransport.__init__(self)
        self.func = func

    def sendMessage(self, message: bytes) -> Any:
        return self.func(message)


class ThriftClientProtocol(basic.Int32StringReceiver):

    MAX_LENGTH = 2 ** 31 - 1
    factory: _ClientFactory

    def __init__(self, client_class: Any, iprot_factory: Any, oprot_factory: Any | None = None) -> None:
        self._client_class = client_class
        self._iprot_factory = iprot_factory
        if oprot_factory is None:
            self._oprot_factory = iprot_factory
        else:
            self._oprot_factory = oprot_factory

        self.recv_map: dict[str, Any] = {}
        self.started = defer.Deferred()
        self.client: Any | None = None

    def dispatch(self, msg: bytes) -> None:
        self.sendString(msg)

    def connectionMade(self) -> None:
        tmo = TCallbackTransport(self.dispatch)
        self.client = self._client_class(tmo, self._oprot_factory)
        self.started.callback(self.client)

    def connectionLost(self, reason: Any = connectionDone) -> None:
        # the called errbacks can add items to our client's _reqs,
        # so we need to use a tmp, and iterate until no more requests
        # are added during errbacks
        if self.client:
            tex = TTransport.TTransportException(
                type=TTransport.TTransportException.END_OF_FILE,
                message='Connection closed (%s)' % reason)
            while self.client._reqs:
                _, v = self.client._reqs.popitem()
                v.errback(tex)
            del self.client._reqs
            self.client = None

    def stringReceived(self, string: bytes) -> None:
        tr = TTransport.TMemoryBuffer(string)
        iprot = self._iprot_factory.getProtocol(tr)
        (fname, mtype, rseqid) = iprot.readMessageBegin()

        try:
            method = self.recv_map[fname]
        except KeyError:
            method = getattr(self.client, 'recv_' + fname)
            self.recv_map[fname] = method

        method(iprot, mtype, rseqid)


class ThriftSASLClientProtocol(ThriftClientProtocol):

    START = 1
    OK = 2
    BAD = 3
    ERROR = 4
    COMPLETE = 5

    MAX_LENGTH = 2 ** 31 - 1

    def __init__(self, client_class: Any, iprot_factory: Any, oprot_factory: Any | None = None,
                 host: str | None = None, service: str | None = None, mechanism: str = 'GSSAPI', **sasl_kwargs: Any) -> None:
        """
        host: the name of the server, from a SASL perspective
        service: the name of the server's service, from a SASL perspective
        mechanism: the name of the preferred mechanism to use

        All other kwargs will be passed to the puresasl.client.SASLClient
        constructor.
        """

        from puresasl.client import SASLClient
        self.SASLClient = SASLClient

        ThriftClientProtocol.__init__(self, client_class, iprot_factory, oprot_factory)

        self._sasl_negotiation_deferred: Any | None = None
        self._sasl_negotiation_status: int | None = None
        self.client: Any | None = None

        if host is not None:
            self.createSASLClient(host, service, mechanism, **sasl_kwargs)

    def createSASLClient(self, host: str, service: str | None, mechanism: str, **kwargs: Any) -> None:
        self.sasl = self.SASLClient(host, service, mechanism, **kwargs)

    def dispatch(self, msg: bytes) -> None:
        encoded = self.sasl.wrap(msg)
        len_and_encoded = struct.pack('!i', len(encoded)) + encoded
        ThriftClientProtocol.dispatch(self, len_and_encoded)

    @defer.inlineCallbacks
    def connectionMade(self) -> Any:
        self._sendSASLMessage(self.START, self.sasl.mechanism)
        initial_message = yield deferToThread(self.sasl.process)
        self._sendSASLMessage(self.OK, initial_message)

        while True:
            status, challenge = yield self._receiveSASLMessage()
            if status == self.OK:
                response = yield deferToThread(self.sasl.process, challenge)
                self._sendSASLMessage(self.OK, response)
            elif status == self.COMPLETE:
                if not self.sasl.complete:
                    msg = "The server erroneously indicated that SASL " \
                          "negotiation was complete"
                    raise TTransport.TTransportException(message=msg)
                else:
                    break
            else:
                msg = "Bad SASL negotiation status: %d (%s)" % (status, challenge)
                raise TTransport.TTransportException(message=msg)

        self._sasl_negotiation_deferred = None
        ThriftClientProtocol.connectionMade(self)

    def _sendSASLMessage(self, status: int, body: bytes | str | None) -> None:
        if body is None:
            body_bytes = b""
        elif isinstance(body, str):
            body_bytes = body.encode("utf-8")
        else:
            body_bytes = body
        header = struct.pack(">BI", status, len(body_bytes))
        transport = cast(_TwistedTransport, self.transport)
        transport.write(header + body_bytes)

    def _receiveSASLMessage(self) -> Any:
        self._sasl_negotiation_deferred = defer.Deferred()
        self._sasl_negotiation_status = None
        return self._sasl_negotiation_deferred

    def connectionLost(self, reason: Any = connectionDone) -> None:
        if self.client:
            ThriftClientProtocol.connectionLost(self, reason)

    def dataReceived(self, data: bytes) -> None:
        if self._sasl_negotiation_deferred:
            # we got a sasl challenge in the format (status, length, challenge)
            # save the status, let IntNStringReceiver piece the challenge data together
            self._sasl_negotiation_status, = struct.unpack("B", data[:1])
            ThriftClientProtocol.dataReceived(self, data[1:])
        else:
            # normal frame, let IntNStringReceiver piece it together
            ThriftClientProtocol.dataReceived(self, data)

    def stringReceived(self, string: bytes) -> None:
        if self._sasl_negotiation_deferred:
            # the frame is just a SASL challenge
            response = (self._sasl_negotiation_status, string)
            self._sasl_negotiation_deferred.callback(response)
        else:
            # there's a second 4 byte length prefix inside the frame
            decoded_frame = self.sasl.unwrap(string[4:])
            ThriftClientProtocol.stringReceived(self, decoded_frame)


class ThriftServerProtocol(basic.Int32StringReceiver):

    MAX_LENGTH = 2 ** 31 - 1
    factory: _ServerFactory

    def dispatch(self, msg: bytes) -> None:
        self.sendString(msg)

    def processError(self, error: Any) -> None:
        transport = cast(_TwistedTransport, self.transport)
        transport.loseConnection()

    def processOk(self, _: Any, tmo: Any) -> None:
        msg = tmo.getvalue()

        if len(msg) > 0:
            self.dispatch(msg)

    def stringReceived(self, string: bytes) -> None:
        tmi = TTransport.TMemoryBuffer(string)
        tmo = TTransport.TMemoryBuffer()

        iprot = self.factory.iprot_factory.getProtocol(tmi)
        oprot = self.factory.oprot_factory.getProtocol(tmo)

        d = self.factory.processor.process(iprot, oprot)
        d.addCallbacks(self.processOk, self.processError,
                       callbackArgs=(tmo,))


class IThriftServerFactory(_InterfaceBase):

    processor = Attribute("Thrift processor")

    iprot_factory = Attribute("Input protocol factory")

    oprot_factory = Attribute("Output protocol factory")


class IThriftClientFactory(_InterfaceBase):

    client_class = Attribute("Thrift client class")

    iprot_factory = Attribute("Input protocol factory")

    oprot_factory = Attribute("Output protocol factory")


@implementer(IThriftServerFactory)
class ThriftServerFactory(ServerFactory):

    protocol = ThriftServerProtocol

    def __init__(self, processor: Any, iprot_factory: Any, oprot_factory: Any | None = None) -> None:
        self.processor = processor
        self.iprot_factory = iprot_factory
        if oprot_factory is None:
            self.oprot_factory = iprot_factory
        else:
            self.oprot_factory = oprot_factory


@implementer(IThriftClientFactory)
class ThriftClientFactory(ClientFactory):

    protocol = ThriftClientProtocol

    def __init__(self, client_class: Any, iprot_factory: Any, oprot_factory: Any | None = None) -> None:
        self.client_class = client_class
        self.iprot_factory = iprot_factory
        if oprot_factory is None:
            self.oprot_factory = iprot_factory
        else:
            self.oprot_factory = oprot_factory

    def buildProtocol(self, addr: Any) -> ThriftClientProtocol:
        p = self.protocol(self.client_class, self.iprot_factory,
                          self.oprot_factory)
        p.factory = self
        return p


class ThriftResource(resource.Resource):

    allowedMethods = ('POST',)

    def __init__(self, processor: Any, inputProtocolFactory: Any,
                 outputProtocolFactory: Any | None = None) -> None:
        resource.Resource.__init__(self)
        self.inputProtocolFactory = inputProtocolFactory
        if outputProtocolFactory is None:
            self.outputProtocolFactory = inputProtocolFactory
        else:
            self.outputProtocolFactory = outputProtocolFactory
        self.processor = processor

    def getChild(self, path: Any, request: Any) -> ThriftResource:
        return self

    def _cbProcess(self, _: Any, request: Any, tmo: Any) -> None:
        msg = tmo.getvalue()
        request.setResponseCode(http.OK)
        request.setHeader("content-type", "application/x-thrift")
        request.write(msg)
        request.finish()

    def render_POST(self, request: Any) -> Any:
        request.content.seek(0, 0)
        data = request.content.read()
        tmi = TTransport.TMemoryBuffer(data)
        tmo = TTransport.TMemoryBuffer()

        iprot = self.inputProtocolFactory.getProtocol(tmi)
        oprot = self.outputProtocolFactory.getProtocol(tmo)

        d = self.processor.process(iprot, oprot)
        d.addCallback(self._cbProcess, request, tmo)
        return server.NOT_DONE_YET
