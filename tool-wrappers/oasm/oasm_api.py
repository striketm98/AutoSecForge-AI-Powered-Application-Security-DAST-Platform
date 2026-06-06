"""AutoSecForge OASM microservice — port 6200"""
import subprocess, re
from flask import Flask, request, jsonify
app = Flask(__name__)

DOMAIN_RE = re.compile(r'^[a-zA-Z0-9.\-]{4,253}$')

@app.get('/health')
def health(): return jsonify(status='ok', service='oasm', version='12.0')

@app.post('/discover')
def discover():
    data   = request.get_json(force=True) or {}
    domain = (data.get('domain') or '').strip()
    if not domain or not DOMAIN_RE.match(domain):
        return jsonify(error='Invalid domain'), 400
    try:
        nmap_out = subprocess.run(
            ['nmap', '-sV', '-T4', '--open', domain],
            capture_output=True, text=True, timeout=300
        )
        return jsonify(success=True, domain=domain, ports=nmap_out.stdout[:10000])
    except Exception as e:
        return jsonify(success=False, error=str(e)), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=6200, debug=False)
