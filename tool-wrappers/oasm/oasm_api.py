#!/usr/bin/env python3
"""
AutoSecForge OASM — Open Attack-Surface Mapping service.

Passive + light-active discovery for a public domain:
  • Subdomains    — Certificate Transparency logs (crt.sh), passive.
  • DNS records   — A / AAAA / MX / NS / TXT.
  • HTTP exposure — server banner + missing security headers (as findings).

Internal-only service (no host port). The PHP app validates the target is a
public host before calling /discover; we re-check here as defence in depth.
"""
import re
import socket
import ipaddress
from concurrent.futures import ThreadPoolExecutor

import requests
from flask import Flask, request, jsonify

try:
    import dns.resolver  # dnspython, optional
    _HAVE_DNS = True
except Exception:
    _HAVE_DNS = False

app = Flask(__name__)

HOST_RE = re.compile(
    r'^(?=.{1,253}$)([a-zA-Z0-9](-?[a-zA-Z0-9]){0,62})(\.[a-zA-Z0-9](-?[a-zA-Z0-9]){0,62})+$'
)

# header → (severity, title, remediation)
SEC_HEADERS = {
    'strict-transport-security': ('high',   'HSTS header missing',
                                  'Add Strict-Transport-Security to force HTTPS and prevent SSL-strip.'),
    'content-security-policy':   ('medium', 'Content-Security-Policy missing',
                                  'Add a CSP to mitigate XSS and data-injection.'),
    'x-frame-options':           ('medium', 'Clickjacking protection missing',
                                  'Add X-Frame-Options: DENY (or CSP frame-ancestors).'),
    'x-content-type-options':    ('low',    'MIME-sniffing protection missing',
                                  'Add X-Content-Type-Options: nosniff.'),
    'referrer-policy':           ('low',    'Referrer-Policy missing',
                                  'Add a Referrer-Policy header (e.g. strict-origin-when-cross-origin).'),
}


def is_public_host(host: str) -> bool:
    """Reject malformed hosts and anything that resolves to a private/loopback IP."""
    host = (host or '').strip().lower()
    if not HOST_RE.match(host):
        return False
    try:
        for info in socket.getaddrinfo(host, None):
            ip = ipaddress.ip_address(info[4][0])
            if ip.is_private or ip.is_loopback or ip.is_link_local or ip.is_reserved:
                return False
    except Exception:
        # Unresolvable is still a public-namespace name; downstream scanners cope.
        return True
    return True


def subdomains_crtsh(domain: str) -> list:
    """Passive subdomain enumeration via crt.sh certificate-transparency JSON."""
    found = set()
    try:
        r = requests.get(f'https://crt.sh/?q=%25.{domain}&output=json', timeout=20)
        if r.ok:
            for row in r.json():
                for name in str(row.get('name_value', '')).splitlines():
                    name = name.strip().lstrip('*.').lower()
                    if name.endswith('.' + domain) or name == domain:
                        if HOST_RE.match(name):
                            found.add(name)
    except Exception:
        pass
    return sorted(found)[:300]


def dns_records(host: str) -> dict:
    rec = {'A': [], 'AAAA': [], 'MX': [], 'NS': [], 'TXT': []}
    # A/AAAA always available via socket.
    try:
        for info in socket.getaddrinfo(host, None):
            ip = info[4][0]
            key = 'AAAA' if ':' in ip else 'A'
            if ip not in rec[key]:
                rec[key].append(ip)
    except Exception:
        pass
    if _HAVE_DNS:
        for rtype in ('MX', 'NS', 'TXT'):
            try:
                ans = dns.resolver.resolve(host, rtype, lifetime=8)
                rec[rtype] = [str(r).strip('"') for r in ans][:20]
            except Exception:
                pass
    return rec


def http_fingerprint(host: str) -> dict:
    """Fetch the site over HTTPS (fallback HTTP) and report banner + header gaps."""
    result = {'url': None, 'server': '', 'status': None, 'findings': []}
    for scheme in ('https', 'http'):
        url = f'{scheme}://{host}'
        try:
            r = requests.get(url, timeout=12, allow_redirects=True,
                             headers={'User-Agent': 'AutoSecForge-OASM/1.0'})
        except Exception:
            continue
        result['url'] = r.url
        result['status'] = r.status_code
        result['server'] = r.headers.get('Server', '')
        present = {k.lower() for k in r.headers}
        for hdr, (sev, title, fix) in SEC_HEADERS.items():
            if hdr not in present:
                result['findings'].append({
                    'title': title,
                    'severity': sev,
                    'affected_url': result['url'],
                    'remediation': fix,
                    'description': f'Response from {result["url"]} did not set the {hdr} header.',
                })
        # Only HSTS over plaintext is interesting; stop after the first reachable scheme.
        break
    return result


@app.route('/health')
def health():
    return jsonify(status='ok', service='autosecforge-oasm', version='1.0', dns=_HAVE_DNS)


@app.route('/discover', methods=['POST'])
def discover():
    data = request.get_json(silent=True) or {}
    host = str(data.get('target', '')).strip().lower()
    # normalise scheme/path away
    host = re.sub(r'^https?://', '', host).split('/')[0].split(':')[0]

    if not is_public_host(host):
        return jsonify(error='Invalid or non-public target.'), 400

    # Run the three probes concurrently — they're independent I/O.
    with ThreadPoolExecutor(max_workers=3) as ex:
        f_sub  = ex.submit(subdomains_crtsh, host)
        f_dns  = ex.submit(dns_records, host)
        f_http = ex.submit(http_fingerprint, host)
        subs, dns_rec, http = f_sub.result(), f_dns.result(), f_http.result()

    return jsonify(
        target=host,
        subdomains=subs,
        subdomain_count=len(subs),
        dns=dns_rec,
        http=http,
        findings=http.get('findings', []),
    )


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=6200)
