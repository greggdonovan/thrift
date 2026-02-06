<?php
/**
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

return [
    'target_php_version' => '8.2',

    'directory_list' => [
        'lib/',
    ],

    'exclude_analysis_directory_list' => [
        'vendor/',
    ],

    'plugins' => [
        'AlwaysReturnPlugin',
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'DuplicateExpressionPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
        'SleepCheckerPlugin',
        'UnreachableCodePlugin',
        'UseReturnValuePlugin',
        'EmptyStatementListPlugin',
        'LoopVariableReusePlugin',
    ],

    // Relaxed checking for legacy code compatibility
    'strict_method_checking' => false,
    'strict_param_checking' => false,
    'strict_property_checking' => false,
    'strict_return_checking' => false,

    'analyze_signature_compatibility' => true,
    'allow_missing_properties' => false,
    'null_casts_as_any_type' => true,
    'null_casts_as_array' => false,
    'array_casts_as_null' => false,
    'scalar_implicit_cast' => false,

    'dead_code_detection' => false,
    'unused_variable_detection' => false,

    // Minimum severity to report (0-10, higher = more severe)
    // 5 = only report critical issues like syntax errors
    'minimum_severity' => 5,

    'suppress_issue_types' => [
        // Suppress issues for pre-existing code patterns
        'PhanUndeclaredProperty',
        'PhanPossiblyUndeclaredVariable',
        'PhanTypeMismatchArgument',
        'PhanTypeMismatchArgumentNullable',
        'PhanTypeMismatchArgumentReal',
        'PhanTypeMismatchArgumentInternal',
        'PhanTypeMismatchReturnNullable',
        'PhanTypeMismatchDimAssignment',
        'PhanTypeMismatchDeclaredParamNullable',
        'PhanPartialTypeMismatchArgument',
        'PhanPartialTypeMismatchArgumentInternal',
        'PhanPartialTypeMismatchReturn',
        'PhanPossiblyNonClassMethodCall',
        'PhanPossiblyNullTypeArgumentInternal',
        'PhanPossiblyFalseTypeArgumentInternal',
        'PhanPossiblyFalseTypeArgument',
        'PhanNonClassMethodCall',
        'PhanTypePossiblyInvalidCallable',
        'PhanParamTooMany',
        'PhanUnreferencedUseNormal',
        'PhanPluginDuplicateConditionalNullCoalescing',
    ],
];
