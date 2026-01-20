/**
 * A thrift file with complex nested types for exercising generated type hints.
 */

namespace py TypeHintsTest
namespace py.twisted TypeHintsTest

struct Inner {
  1: required i32 id
  2: optional string label
}

typedef map<string, list<Inner>> InnerMap

enum Status {
  OK = 1,
  WARN = 2,
  ERROR = 3,
}

union Payload {
  1: i64 count
  2: string note
  3: Inner inner
}

struct Container {
  1: required InnerMap inner_map
  2: optional list<set<uuid>> uuid_sets
  3: optional Payload payload
  4: required list<map<string, list<i64>>> nested_numbers
  5: optional Status status
}

service TypeHintsTest {
  Container ping(1: required InnerMap data, 2: optional Payload payload)
  list<Container> batch(1: list<Container> items)
}
