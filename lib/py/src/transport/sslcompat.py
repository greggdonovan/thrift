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

"""SSL compatibility utilities for Thrift.

For Python 3.10+, hostname verification is handled by OpenSSL during the
TLS handshake when SSLContext.check_hostname is True. This module provides
TLS version enforcement utilities.
"""

import ssl

# Minimum TLS version for all Thrift SSL connections
MINIMUM_TLS_VERSION = ssl.TLSVersion.TLSv1_2


def enforce_minimum_tls(context):
    """Enforce TLS 1.2 or higher on an SSLContext.

    This function modifies the context in-place to ensure that TLS 1.2 or higher
    is used. It raises ValueError if the context's maximum_version is set to a
    version lower than TLS 1.2.

    Args:
        context: An ssl.SSLContext to enforce minimum TLS version on
    """
    if context.minimum_version < MINIMUM_TLS_VERSION:
        context.minimum_version = MINIMUM_TLS_VERSION
    if (context.maximum_version != ssl.TLSVersion.MAXIMUM_SUPPORTED and
            context.maximum_version < MINIMUM_TLS_VERSION):
        raise ValueError('TLS maximum_version must be TLS 1.2 or higher.')


def validate_minimum_tls(context):
    """Validate that an SSLContext uses TLS 1.2 or higher.

    Unlike enforce_minimum_tls, this function does not modify the context.
    It raises ValueError if the context is configured to use TLS versions
    lower than 1.2.

    Args:
        context: An ssl.SSLContext to validate

    Raises:
        ValueError: If the context allows TLS versions below 1.2
    """
    if context.minimum_version < MINIMUM_TLS_VERSION:
        raise ValueError(
            'ssl_context.minimum_version must be TLS 1.2 or higher.')
    if (context.maximum_version != ssl.TLSVersion.MAXIMUM_SUPPORTED and
            context.maximum_version < MINIMUM_TLS_VERSION):
        raise ValueError(
            'ssl_context.maximum_version must be TLS 1.2 or higher.')
