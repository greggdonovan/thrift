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

from typing import Any, Callable, Collection, Iterable, Iterator, Literal, Mapping, Sequence, TypeAlias, TypeVar, overload
from uuid import UUID

from thrift.Thrift import TException, TType, TFrozenDict
from thrift.transport.TTransport import TTransportBase, TTransportException

import sys
from itertools import islice

MessageTuple: TypeAlias = tuple[str, int, int]
FieldTuple: TypeAlias = tuple[str | None, int, int]
MapBeginTuple: TypeAlias = tuple[int, int, int]
ListBeginTuple: TypeAlias = tuple[int, int]
SetBeginTuple: TypeAlias = tuple[int, int]
ThriftSpec: TypeAlias = Sequence[Any]

TStruct = TypeVar("TStruct")


class TProtocolException(TException):
    """Custom Protocol Exception class"""

    UNKNOWN = 0
    INVALID_DATA = 1
    NEGATIVE_SIZE = 2
    SIZE_LIMIT = 3
    BAD_VERSION = 4
    NOT_IMPLEMENTED = 5
    DEPTH_LIMIT = 6
    INVALID_PROTOCOL = 7

    def __init__(self, type: int = UNKNOWN, message: str | None = None) -> None:
        TException.__init__(self, message)
        self.type = type


class TProtocolBase(object):
    """Base class for Thrift protocol driver."""

    def __init__(self, trans: TTransportBase) -> None:
        self.trans = trans
        self._fast_decode: Callable[..., Any] | None = None
        self._fast_encode: Callable[..., bytes] | None = None

    @staticmethod
    def _check_length(limit: int | None, length: int) -> None:
        if length < 0:
            raise TTransportException(TTransportException.NEGATIVE_SIZE,
                                      'Negative length: %d' % length)
        if limit is not None and length > limit:
            raise TTransportException(TTransportException.SIZE_LIMIT,
                                      'Length exceeded max allowed: %d' % limit)

    def writeMessageBegin(self, name: str, ttype: int, seqid: int) -> None:
        raise NotImplementedError()

    def writeMessageEnd(self) -> None:
        raise NotImplementedError()

    def writeStructBegin(self, name: str) -> None:
        raise NotImplementedError()

    def writeStructEnd(self) -> None:
        raise NotImplementedError()

    def writeFieldBegin(self, name: str, ttype: int, fid: int) -> None:
        raise NotImplementedError()

    def writeFieldEnd(self) -> None:
        raise NotImplementedError()

    def writeFieldStop(self) -> None:
        raise NotImplementedError()

    def writeMapBegin(self, ktype: int, vtype: int, size: int) -> None:
        raise NotImplementedError()

    def writeMapEnd(self) -> None:
        raise NotImplementedError()

    def writeListBegin(self, etype: int, size: int) -> None:
        raise NotImplementedError()

    def writeListEnd(self) -> None:
        raise NotImplementedError()

    def writeSetBegin(self, etype: int, size: int) -> None:
        raise NotImplementedError()

    def writeSetEnd(self) -> None:
        raise NotImplementedError()

    def writeBool(self, bool_val: bool) -> None:
        raise NotImplementedError()

    def writeByte(self, byte: int) -> None:
        raise NotImplementedError()

    def writeI16(self, i16: int) -> None:
        raise NotImplementedError()

    def writeI32(self, i32: int) -> None:
        raise NotImplementedError()

    def writeI64(self, i64: int) -> None:
        raise NotImplementedError()

    def writeDouble(self, dub: float) -> None:
        raise NotImplementedError()

    def writeString(self, str_val: str) -> None:
        self.writeBinary(bytes(str_val, 'utf-8'))

    def writeBinary(self, str_val: bytes) -> None:
        raise NotImplementedError()

    def writeUuid(self, uuid_val: UUID) -> None:
        raise NotImplementedError()

    def readMessageBegin(self) -> MessageTuple:
        raise NotImplementedError()

    def readMessageEnd(self) -> None:
        raise NotImplementedError()

    def readStructBegin(self) -> str | None:
        raise NotImplementedError()

    def readStructEnd(self) -> None:
        raise NotImplementedError()

    def readFieldBegin(self) -> FieldTuple:
        raise NotImplementedError()

    def readFieldEnd(self) -> None:
        raise NotImplementedError()

    def readMapBegin(self) -> MapBeginTuple:
        raise NotImplementedError()

    def readMapEnd(self) -> None:
        raise NotImplementedError()

    def readListBegin(self) -> ListBeginTuple:
        raise NotImplementedError()

    def readListEnd(self) -> None:
        raise NotImplementedError()

    def readSetBegin(self) -> SetBeginTuple:
        raise NotImplementedError()

    def readSetEnd(self) -> None:
        raise NotImplementedError()

    def readBool(self) -> bool:
        raise NotImplementedError()

    def readByte(self) -> int:
        raise NotImplementedError()

    def readI16(self) -> int:
        raise NotImplementedError()

    def readI32(self) -> int:
        raise NotImplementedError()

    def readI64(self) -> int:
        raise NotImplementedError()

    def readDouble(self) -> float:
        raise NotImplementedError()

    def readString(self) -> str:
        return self.readBinary().decode('utf-8')

    def readBinary(self) -> bytes:
        raise NotImplementedError()

    def readUuid(self) -> UUID:
        raise NotImplementedError()

    def skip(self, ttype: int) -> None:
        if ttype == TType.BOOL:
            self.readBool()
        elif ttype == TType.BYTE:
            self.readByte()
        elif ttype == TType.I16:
            self.readI16()
        elif ttype == TType.I32:
            self.readI32()
        elif ttype == TType.I64:
            self.readI64()
        elif ttype == TType.DOUBLE:
            self.readDouble()
        elif ttype == TType.STRING:
            self.readString()
        elif ttype == TType.UUID:
            self.readUuid()
        elif ttype == TType.STRUCT:
            name = self.readStructBegin()
            while True:
                (name, ttype, id) = self.readFieldBegin()
                if ttype == TType.STOP:
                    break
                self.skip(ttype)
                self.readFieldEnd()
            self.readStructEnd()
        elif ttype == TType.MAP:
            (ktype, vtype, size) = self.readMapBegin()
            for i in range(size):
                self.skip(ktype)
                self.skip(vtype)
            self.readMapEnd()
        elif ttype == TType.SET:
            (etype, size) = self.readSetBegin()
            for i in range(size):
                self.skip(etype)
            self.readSetEnd()
        elif ttype == TType.LIST:
            (etype, size) = self.readListBegin()
            for i in range(size):
                self.skip(etype)
            self.readListEnd()
        else:
            raise TProtocolException(
                TProtocolException.INVALID_DATA,
                "invalid TType")

    # tuple of: ( 'reader method' name, is_container bool, 'writer_method' name )
    _TTYPE_HANDLERS = (
        (None, None, False),  # 0 TType.STOP
        (None, None, False),  # 1 TType.VOID # TODO: handle void?
        ('readBool', 'writeBool', False),  # 2 TType.BOOL
        ('readByte', 'writeByte', False),  # 3 TType.BYTE and I08
        ('readDouble', 'writeDouble', False),  # 4 TType.DOUBLE
        (None, None, False),  # 5 undefined
        ('readI16', 'writeI16', False),  # 6 TType.I16
        (None, None, False),  # 7 undefined
        ('readI32', 'writeI32', False),  # 8 TType.I32
        (None, None, False),  # 9 undefined
        ('readI64', 'writeI64', False),  # 10 TType.I64
        ('readString', 'writeString', False),  # 11 TType.STRING and UTF7
        ('readContainerStruct', 'writeContainerStruct', True),  # 12 *.STRUCT
        ('readContainerMap', 'writeContainerMap', True),  # 13 TType.MAP
        ('readContainerSet', 'writeContainerSet', True),  # 14 TType.SET
        ('readContainerList', 'writeContainerList', True),  # 15 TType.LIST
        ('readUuid', 'writeUuid', False),  # 16 TType.UUID
        (None, None, False)  # 17 TType.UTF16 # TODO: handle utf16 types?
    )

    def _ttype_handlers(self, ttype: int, spec: Any) -> tuple[str | None, str | None, bool]:
        if spec == 'BINARY':
            if ttype != TType.STRING:
                raise TProtocolException(type=TProtocolException.INVALID_DATA,
                                         message='Invalid binary field type %d' % ttype)
            return ('readBinary', 'writeBinary', False)
        return self._TTYPE_HANDLERS[ttype] if ttype < len(self._TTYPE_HANDLERS) else (None, None, False)

    def _read_by_ttype(self, ttype: int, spec: Any, espec: Any) -> Iterator[Any]:
        reader_name, _, is_container = self._ttype_handlers(ttype, espec)
        if reader_name is None:
            raise TProtocolException(type=TProtocolException.INVALID_DATA,
                                     message='Invalid type %d' % (ttype))
        reader_func = getattr(self, reader_name)
        read = (lambda: reader_func(espec)) if is_container else reader_func
        while True:
            yield read()

    def readFieldByTType(self, ttype: int, spec: Any) -> Any:
        return next(self._read_by_ttype(ttype, spec, spec))

    def readContainerList(self, spec: Any) -> list[Any] | tuple[Any, ...]:
        ttype, tspec, is_immutable = spec
        (list_type, list_len) = self.readListBegin()
        # TODO: compare types we just decoded with thrift_spec
        elems = islice(self._read_by_ttype(ttype, spec, tspec), list_len)
        results = (tuple if is_immutable else list)(elems)
        self.readListEnd()
        return results

    def readContainerSet(self, spec: Any) -> set[Any] | frozenset[Any]:
        ttype, tspec, is_immutable = spec
        (set_type, set_len) = self.readSetBegin()
        # TODO: compare types we just decoded with thrift_spec
        elems = islice(self._read_by_ttype(ttype, spec, tspec), set_len)
        results = (frozenset if is_immutable else set)(elems)
        self.readSetEnd()
        return results

    def readContainerStruct(self, spec: Any) -> Any:
        (obj_class, obj_spec) = spec

        # If obj_class.read is a classmethod (e.g. in frozen structs),
        # call it as such.
        if getattr(obj_class.read, '__self__', None) is obj_class:
            obj = obj_class.read(self)
        else:
            obj = obj_class()
            obj.read(self)
        return obj

    def readContainerMap(self, spec: Any) -> dict[Any, Any] | TFrozenDict[Any, Any]:
        ktype, kspec, vtype, vspec, is_immutable = spec
        (map_ktype, map_vtype, map_len) = self.readMapBegin()
        # TODO: compare types we just decoded with thrift_spec and
        # abort/skip if types disagree
        keys = self._read_by_ttype(ktype, spec, kspec)
        vals = self._read_by_ttype(vtype, spec, vspec)
        keyvals = islice(zip(keys, vals), map_len)
        results = (TFrozenDict if is_immutable else dict)(keyvals)
        self.readMapEnd()
        return results

    @overload
    def readStruct(self, obj: TStruct, thrift_spec: ThriftSpec, is_immutable: Literal[False] = False) -> None:
        ...

    @overload
    def readStruct(self, obj: type[TStruct], thrift_spec: ThriftSpec, is_immutable: Literal[True]) -> TStruct:
        ...

    def readStruct(self, obj: Any, thrift_spec: ThriftSpec, is_immutable: bool = False) -> Any:
        if is_immutable:
            fields = {}
        self.readStructBegin()
        while True:
            (fname, ftype, fid) = self.readFieldBegin()
            if ftype == TType.STOP:
                break
            try:
                field = thrift_spec[fid]
            except IndexError:
                self.skip(ftype)
            else:
                if field is not None and ftype == field[1]:
                    fname = field[2]
                    fspec = field[3]
                    val = self.readFieldByTType(ftype, fspec)
                    if is_immutable:
                        fields[fname] = val
                    else:
                        setattr(obj, fname, val)
                else:
                    self.skip(ftype)
            self.readFieldEnd()
        self.readStructEnd()
        if is_immutable:
            return obj(**fields)

    def writeContainerStruct(self, val: Any, spec: Any) -> None:
        val.write(self)

    def writeContainerList(self, val: Collection[Any], spec: Any) -> None:
        ttype, tspec, _ = spec
        self.writeListBegin(ttype, len(val))
        for _ in self._write_by_ttype(ttype, val, spec, tspec):
            pass
        self.writeListEnd()

    def writeContainerSet(self, val: Collection[Any], spec: Any) -> None:
        ttype, tspec, _ = spec
        self.writeSetBegin(ttype, len(val))
        for _ in self._write_by_ttype(ttype, val, spec, tspec):
            pass
        self.writeSetEnd()

    def writeContainerMap(self, val: Mapping[Any, Any], spec: Any) -> None:
        ktype, kspec, vtype, vspec, _ = spec
        self.writeMapBegin(ktype, vtype, len(val))
        for _ in zip(self._write_by_ttype(ktype, val.keys(), spec, kspec),
                     self._write_by_ttype(vtype, val.values(), spec, vspec)):
            pass
        self.writeMapEnd()

    def writeStruct(self, obj: Any, thrift_spec: ThriftSpec) -> None:
        self.writeStructBegin(obj.__class__.__name__)
        for field in thrift_spec:
            if field is None:
                continue
            fname = field[2]
            val = getattr(obj, fname)
            if val is None:
                # skip writing out unset fields
                continue
            fid = field[0]
            ftype = field[1]
            fspec = field[3]
            self.writeFieldBegin(fname, ftype, fid)
            self.writeFieldByTType(ftype, val, fspec)
            self.writeFieldEnd()
        self.writeFieldStop()
        self.writeStructEnd()

    def _write_by_ttype(self, ttype: int, vals: Iterable[Any], spec: Any, espec: Any) -> Iterator[Any]:
        _, writer_name, is_container = self._ttype_handlers(ttype, espec)
        if writer_name is None:
            raise TProtocolException(type=TProtocolException.INVALID_DATA,
                                     message='Invalid type %d' % (ttype))
        writer_func = getattr(self, writer_name)
        write = (lambda v: writer_func(v, espec)) if is_container else writer_func
        for v in vals:
            yield write(v)

    def writeFieldByTType(self, ttype: int, val: Any, spec: Any) -> None:
        next(self._write_by_ttype(ttype, [val], spec, spec))


def checkIntegerLimits(i: int, bits: int) -> None:
    if bits == 8 and (i < -128 or i > 127):
        raise TProtocolException(TProtocolException.INVALID_DATA,
                                 "i8 requires -128 <= number <= 127")
    elif bits == 16 and (i < -32768 or i > 32767):
        raise TProtocolException(TProtocolException.INVALID_DATA,
                                 "i16 requires -32768 <= number <= 32767")
    elif bits == 32 and (i < -2147483648 or i > 2147483647):
        raise TProtocolException(TProtocolException.INVALID_DATA,
                                 "i32 requires -2147483648 <= number <= 2147483647")
    elif bits == 64 and (i < -9223372036854775808 or i > 9223372036854775807):
        raise TProtocolException(TProtocolException.INVALID_DATA,
                                 "i64 requires -9223372036854775808 <= number <= 9223372036854775807")


class TProtocolFactory(object):
    def getProtocol(self, trans: TTransportBase) -> TProtocolBase:
        raise NotImplementedError()
