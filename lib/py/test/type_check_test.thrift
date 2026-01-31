/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements. See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership. The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

/**
 * Comprehensive test thrift file for validating Python type hints.
 * Covers all thrift features to ensure generated code passes ty type checking.
 */

namespace py type_check_test

// ============ ENUMS ============
enum Status {
    PENDING = 0,
    ACTIVE = 1,
    DONE = 2,
    CANCELLED = -1,  // Negative value
}

enum Priority {
    LOW = 1,
    MEDIUM = 5,
    HIGH = 10,
    CRITICAL = 100,
}

// ============ TYPEDEFS ============
typedef i64 UserId
typedef string Email
typedef list<string> StringList
typedef map<string, i32> ScoreMap

// ============ STRUCTS ============
struct Empty {}

struct Primitives {
    1: bool boolField,
    2: byte byteField,
    3: i16 i16Field,
    4: i32 i32Field,
    5: i64 i64Field,
    6: double doubleField,
    7: string stringField,
    8: binary binaryField,
}

struct RequiredFields {
    1: required string name,
    2: required i32 id,
    3: required Status status,
}

struct OptionalFields {
    1: optional string name,
    2: optional i32 count,
    3: optional Status status,
}

struct DefaultValues {
    1: string name = "default",
    2: i32 count = 42,
    3: Status status = Status.PENDING,
    4: list<string> tags = ["a", "b"],
}

struct Containers {
    1: list<string> stringList,
    2: list<i32> intList,
    3: set<i64> longSet,
    4: set<string> stringSet,
    5: map<string, i32> stringIntMap,
    6: map<i64, string> longStringMap,
}

struct NestedContainers {
    1: list<list<i32>> matrix,
    2: map<string, list<i32>> mapOfLists,
    3: list<map<string, i32>> listOfMaps,
    4: map<string, map<string, i32>> nestedMap,
}

struct NestedStructs {
    1: Primitives primitives,
    2: list<Primitives> primitivesList,
    3: map<string, Primitives> primitivesMap,
}

struct WithEnum {
    1: Status status,
    2: Priority priority,
    3: list<Status> statusList,
    4: map<string, Status> statusMap,
}

struct WithTypedef {
    1: UserId userId,
    2: Email email,
    3: StringList tags,
    4: ScoreMap scores,
}

// ============ UNIONS ============
union TestUnion {
    1: string stringValue,
    2: i32 intValue,
    3: Primitives structValue,
    4: list<string> listValue,
}

// ============ EXCEPTIONS ============
exception ValidationError {
    1: string message,
    2: i32 code,
    3: list<string> fields,
}

exception NotFoundError {
    1: required string resourceType,
    2: required i64 resourceId,
}

// ============ SERVICES ============
service TypeCheckService {
    // Void methods
    void ping(),
    void setStatus(1: i64 id, 2: Status status),

    // Primitive returns
    bool isActive(1: i64 id),
    i32 getCount(),
    i64 getId(1: string name),
    double getScore(1: i64 id),
    string getName(1: i64 id),
    binary getData(1: i64 id),

    // Enum returns
    Status getStatus(1: i64 id),

    // Struct returns
    Primitives getPrimitives(1: i64 id),
    Containers getContainers(1: i64 id),

    // Container returns
    list<string> getTags(1: i64 id),
    set<i64> getIds(),
    map<string, i32> getScores(),

    // Multiple parameters
    void updateUser(1: i64 id, 2: string name, 3: Email email, 4: list<string> tags),

    // With exceptions
    Primitives getOrThrow(1: i64 id) throws (1: NotFoundError notFound, 2: ValidationError validation),

    // Oneway
    oneway void asyncNotify(1: string message),
}

// ============ CONSTANTS ============
const i32 MAX_ITEMS = 1000
const string DEFAULT_NAME = "unnamed"
const list<string> VALID_STATUSES = ["pending", "active", "done"]
const map<string, i32> STATUS_CODES = {"pending": 0, "active": 1, "done": 2}
const Status DEFAULT_STATUS = Status.PENDING
