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
package org.apache.thrift;

import static org.junit.jupiter.api.Assertions.*;

import org.junit.jupiter.api.Test;

/**
 * Tests that verify the runtime environment is JDK 17 or later. This test ensures that the minimum
 * Java version requirement is enforced at runtime.
 */
public class Jdk17Test {

  private static final int MINIMUM_JAVA_VERSION = 17;

  @Test
  public void testJavaVersionIsAtLeast17() {
    int majorVersion = Runtime.version().feature();
    assertTrue(
        majorVersion >= MINIMUM_JAVA_VERSION,
        "Java version must be at least "
            + MINIMUM_JAVA_VERSION
            + " but was "
            + majorVersion
            + ". Apache Thrift Java library requires JDK 17 or later.");
  }

  @Test
  public void testJdk17FeatureRecordClassesAvailable() {
    // Verify that record classes (JDK 16+ feature, finalized in JDK 16) are available
    // by checking that the Record class exists and is accessible
    assertDoesNotThrow(
        () -> Class.forName("java.lang.Record"), "java.lang.Record should be available in JDK 17+");
  }

  @Test
  public void testJdk17FeatureSealedClassesAvailable() {
    // Verify that sealed classes (JDK 17 feature) are available
    // by checking that the Class.isSealed() method exists
    assertDoesNotThrow(
        () -> Class.class.getMethod("isSealed"),
        "Class.isSealed() method should be available in JDK 17+");
  }

  @Test
  public void testJdk17FeaturePatternMatchingInstanceof() {
    // Verify pattern matching for instanceof works (JDK 16+ feature, finalized in JDK 16)
    Object obj = "test string";
    if (obj instanceof String s) {
      assertEquals("test string", s);
    } else {
      fail("Pattern matching for instanceof should work in JDK 17+");
    }
  }

  @Test
  public void testJdk17FeatureTextBlocks() {
    // Verify text blocks work (JDK 15+ feature, finalized in JDK 15)
    String textBlock =
        """
        This is a text block
        that spans multiple lines
        """;
    assertTrue(textBlock.contains("text block"));
    assertTrue(textBlock.contains("\n"));
  }

  @Test
  public void testJdk17FeatureHexFormatAvailable() {
    // Verify HexFormat class is available (JDK 17+ feature)
    assertDoesNotThrow(
        () -> Class.forName("java.util.HexFormat"),
        "java.util.HexFormat should be available in JDK 17+");
  }
}
