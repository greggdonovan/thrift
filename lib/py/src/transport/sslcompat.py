#
# licensed to the apache software foundation (asf) under one
# or more contributor license agreements. see the notice file
# distributed with this work for additional information
# regarding copyright ownership. the asf licenses this file
# to you under the apache license, version 2.0 (the
# "license"); you may not use this file except in compliance
# with the license. you may obtain a copy of the license at
#
#   http://www.apache.org/licenses/license-2.0
#
# unless required by applicable law or agreed to in writing,
# software distributed under the license is distributed on an
# "as is" basis, without warranties or conditions of any
# KIND, either express or implied. See the License for the
# specific language governing permissions and limitations
# under the License.
#

import ipaddress
import logging
import ssl

from thrift.transport.TTransport import TTransportException

logger = logging.getLogger(__name__)


def legacy_validate_callback(cert, hostname):
    """legacy method to validate the peer's SSL certificate, and to check
    the commonName of the certificate to ensure it matches the hostname we
    used to make this connection.  Does not support subjectAltName records
    in certificates.

    raises TTransportException if the certificate fails validation.
    """
    if 'subject' not in cert:
        raise TTransportException(
            TTransportException.NOT_OPEN,
            'No SSL certificate found from %s' % hostname)
    fields = cert['subject']
    for field in fields:
        # ensure structure we get back is what we expect
        if not isinstance(field, tuple):
            continue
        cert_pair = field[0]
        if len(cert_pair) < 2:
            continue
        cert_key, cert_value = cert_pair[0:2]
        if cert_key != 'commonName':
            continue
        certhost = cert_value
        # this check should be performed by some sort of Access Manager
        if certhost == hostname:
            # success, cert commonName matches desired hostname
            return
        else:
            raise TTransportException(
                TTransportException.UNKNOWN,
                'Hostname we connected to "%s" doesn\'t match certificate '
                'provided commonName "%s"' % (hostname, certhost))
    raise TTransportException(
        TTransportException.UNKNOWN,
        'Could not validate SSL certificate from host "%s".  Cert=%s'
        % (hostname, cert))


def _match_hostname(cert, hostname):
    if not cert:
        raise ssl.CertificateError('no peer certificate available')

    try:
        host_ip = ipaddress.ip_address(hostname)
    except ValueError:
        host_ip = None

    dnsnames = []
    san = cert.get('subjectAltName', ())
    for key, value in san:
        if key == 'DNS':
            if host_ip is None and ssl._dnsname_match(value, hostname):
                return
            dnsnames.append(value)
        elif key == 'IP Address':
            if host_ip is not None and ssl._ipaddress_match(value, host_ip.packed):
                return
            dnsnames.append(value)

    if not dnsnames:
        for sub in cert.get('subject', ()):
            for key, value in sub:
                if key == 'commonName':
                    if ssl._dnsname_match(value, hostname):
                        return
                    dnsnames.append(value)

    if dnsnames:
        raise ssl.CertificateError(
            "hostname %r doesn't match %s"
            % (hostname, ', '.join(repr(dn) for dn in dnsnames)))

    raise ssl.CertificateError(
        "no appropriate subjectAltName fields were found")

