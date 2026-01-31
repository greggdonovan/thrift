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

"""
Comprehensive type checking tests for thrift-generated Python code.

Uses Astral's ty type checker to validate that generated code has correct
and complete Python 3.10+ type hints.
"""

import glob
import os
import shutil
import subprocess
import sys
import unittest

# Add thrift library from build directory to path before any imports
# This mirrors the pattern used by other tests in this directory
_TEST_DIR = os.path.dirname(os.path.abspath(__file__))
_ROOT_DIR = os.path.dirname(os.path.dirname(os.path.dirname(_TEST_DIR)))
for _libpath in glob.glob(os.path.join(_ROOT_DIR, "lib", "py", "build", "lib.*")):
    for _pattern in ("-%d.%d", "-%d%d"):
        _postfix = _pattern % (sys.version_info[0], sys.version_info[1])
        if _libpath.endswith(_postfix):
            sys.path.insert(0, _libpath)
            break


def ensure_ty_installed() -> None:
    """Install ty if not available, using uv."""
    if shutil.which("ty") is not None:
        return

    # Try uv first (preferred)
    if shutil.which("uv") is not None:
        subprocess.run(
            ["uv", "tool", "install", "ty"],
            check=True,
            capture_output=True,
        )
    else:
        # Fall back to installing uv first, then ty
        subprocess.run(
            [sys.executable, "-m", "pip", "install", "uv"],
            check=True,
            capture_output=True,
        )
        subprocess.run(
            ["uv", "tool", "install", "ty"],
            check=True,
            capture_output=True,
        )


def find_thrift_compiler() -> str:
    """Find the thrift compiler binary."""
    # Check PATH first
    thrift_bin = shutil.which("thrift")
    if thrift_bin is not None:
        return thrift_bin

    # Try common build directories
    test_dir = os.path.dirname(__file__)
    candidates = [
        os.path.join(test_dir, "..", "..", "..", "build-compiler", "compiler", "cpp", "bin", "thrift"),
        os.path.join(test_dir, "..", "..", "..", "compiler", "cpp", "thrift"),
        os.path.join(test_dir, "..", "..", "..", "build", "compiler", "cpp", "bin", "thrift"),
    ]

    for candidate in candidates:
        abs_path = os.path.abspath(candidate)
        if os.path.exists(abs_path) and os.access(abs_path, os.X_OK):
            return abs_path

    raise RuntimeError(
        "thrift compiler not found. Ensure it is in PATH or built in build-compiler/"
    )


class TypeCheckTest(unittest.TestCase):
    """Tests that validate type hints in generated Python code."""

    gen_dir: str

    @classmethod
    def setUpClass(cls) -> None:
        ensure_ty_installed()

        # Paths
        test_dir = os.path.dirname(__file__)
        thrift_file = os.path.join(test_dir, "type_check_test.thrift")
        cls.gen_dir = os.path.join(test_dir, "gen-py-typecheck")

        # Find thrift compiler
        thrift_bin = find_thrift_compiler()

        # Clean and regenerate
        if os.path.exists(cls.gen_dir):
            shutil.rmtree(cls.gen_dir)
        os.makedirs(cls.gen_dir, exist_ok=True)

        # Run thrift compiler
        result = subprocess.run(
            [thrift_bin, "--gen", "py", "-out", cls.gen_dir, thrift_file],
            capture_output=True,
            text=True,
        )
        if result.returncode != 0:
            raise RuntimeError(
                f"thrift compiler failed:\nstdout: {result.stdout}\nstderr: {result.stderr}"
            )

        # Add generated code to path for import tests
        sys.path.insert(0, cls.gen_dir)

    @classmethod
    def tearDownClass(cls) -> None:
        # Remove generated code from path
        if cls.gen_dir in sys.path:
            sys.path.remove(cls.gen_dir)
        # Clean up generated files
        if os.path.exists(cls.gen_dir):
            shutil.rmtree(cls.gen_dir)

    def test_ty_type_check_passes(self) -> None:
        """Verify generated code passes ty without errors."""
        result = subprocess.run(
            ["ty", "check", self.gen_dir],
            capture_output=True,
            text=True,
        )

        self.assertEqual(
            result.returncode,
            0,
            f"ty check failed:\nstdout: {result.stdout}\nstderr: {result.stderr}",
        )

    def test_py_typed_marker_exists(self) -> None:
        """Verify py.typed marker is generated for PEP 561."""
        py_typed = os.path.join(self.gen_dir, "type_check_test", "py.typed")
        self.assertTrue(
            os.path.exists(py_typed),
            f"py.typed marker missing at {py_typed}",
        )

    def test_generated_code_is_importable(self) -> None:
        """Verify generated code can be imported without errors."""
        from type_check_test import TypeCheckService, constants, ttypes

        # Verify key types exist
        self.assertTrue(hasattr(ttypes, "Status"))
        self.assertTrue(hasattr(ttypes, "Priority"))
        self.assertTrue(hasattr(ttypes, "Primitives"))
        self.assertTrue(hasattr(ttypes, "RequiredFields"))
        self.assertTrue(hasattr(ttypes, "OptionalFields"))
        self.assertTrue(hasattr(ttypes, "DefaultValues"))
        self.assertTrue(hasattr(ttypes, "Containers"))
        self.assertTrue(hasattr(ttypes, "NestedContainers"))
        self.assertTrue(hasattr(ttypes, "NestedStructs"))
        self.assertTrue(hasattr(ttypes, "WithEnum"))
        self.assertTrue(hasattr(ttypes, "WithTypedef"))
        self.assertTrue(hasattr(ttypes, "TestUnion"))
        self.assertTrue(hasattr(ttypes, "ValidationError"))
        self.assertTrue(hasattr(ttypes, "NotFoundError"))
        self.assertTrue(hasattr(ttypes, "Empty"))

        # Verify constants exist
        self.assertTrue(hasattr(constants, "MAX_ITEMS"))
        self.assertTrue(hasattr(constants, "DEFAULT_NAME"))
        self.assertTrue(hasattr(constants, "VALID_STATUSES"))
        self.assertTrue(hasattr(constants, "STATUS_CODES"))
        self.assertTrue(hasattr(constants, "DEFAULT_STATUS"))

        # Verify service exists
        self.assertTrue(hasattr(TypeCheckService, "Client"))
        self.assertTrue(hasattr(TypeCheckService, "Processor"))

    def test_enum_is_intenum(self) -> None:
        """Verify enums are generated as IntEnum."""
        from enum import IntEnum

        from type_check_test import ttypes

        self.assertTrue(issubclass(ttypes.Status, IntEnum))
        self.assertTrue(issubclass(ttypes.Priority, IntEnum))

        # Verify enum values
        self.assertEqual(ttypes.Status.PENDING, 0)
        self.assertEqual(ttypes.Status.ACTIVE, 1)
        self.assertEqual(ttypes.Status.DONE, 2)
        self.assertEqual(ttypes.Status.CANCELLED, -1)

        self.assertEqual(ttypes.Priority.LOW, 1)
        self.assertEqual(ttypes.Priority.CRITICAL, 100)

    def test_struct_instantiation(self) -> None:
        """Verify structs can be instantiated with type-correct arguments."""
        from type_check_test import ttypes

        # Test primitives struct
        p = ttypes.Primitives(
            boolField=True,
            byteField=127,
            i16Field=32767,
            i32Field=2147483647,
            i64Field=9223372036854775807,
            doubleField=3.14,
            stringField="test",
            binaryField=b"bytes",
        )
        self.assertEqual(p.boolField, True)
        self.assertEqual(p.stringField, "test")
        self.assertEqual(p.binaryField, b"bytes")

        # Test containers struct
        c = ttypes.Containers(
            stringList=["a", "b", "c"],
            intList=[1, 2, 3],
            longSet={1, 2, 3},
            stringSet={"a", "b"},
            stringIntMap={"key": 42},
            longStringMap={1: "one"},
        )
        self.assertEqual(c.stringList, ["a", "b", "c"])
        self.assertEqual(c.stringIntMap, {"key": 42})

        # Test required fields struct
        r = ttypes.RequiredFields(
            name="test",
            id=123,
            status=ttypes.Status.ACTIVE,
        )
        self.assertEqual(r.name, "test")
        self.assertEqual(r.status, ttypes.Status.ACTIVE)

        # Test union
        u = ttypes.TestUnion(stringValue="test")
        self.assertEqual(u.stringValue, "test")

    def test_exception_inheritance(self) -> None:
        """Verify exceptions inherit from TException and can be raised."""
        from type_check_test import ttypes

        from thrift.Thrift import TException

        self.assertTrue(issubclass(ttypes.ValidationError, TException))
        self.assertTrue(issubclass(ttypes.NotFoundError, TException))

        # Test raising and catching
        try:
            raise ttypes.ValidationError(
                message="test error",
                code=400,
                fields=["field1", "field2"],
            )
        except ttypes.ValidationError as e:
            self.assertEqual(e.message, "test error")
            self.assertEqual(e.code, 400)
            self.assertEqual(e.fields, ["field1", "field2"])


if __name__ == "__main__":
    unittest.main()
