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
        'test/',
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

    'strict_method_checking' => true,
    'strict_param_checking' => true,
    'strict_property_checking' => true,
    'strict_return_checking' => true,

    'analyze_signature_compatibility' => true,
    'allow_missing_properties' => false,
    'null_casts_as_any_type' => false,
    'null_casts_as_array' => false,
    'array_casts_as_null' => false,
    'scalar_implicit_cast' => false,

    'dead_code_detection' => false,
    'unused_variable_detection' => false,

    'suppress_issue_types' => [
        // Suppress dynamic property issues for TBase until generator is updated
        'PhanUndeclaredProperty',
    ],
];
