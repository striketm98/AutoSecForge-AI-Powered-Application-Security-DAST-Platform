import subprocess, re
from flask import Flask, request, jsonify
app = Flask(__name__)

SAFE_TARGET = re.compile(r'^[a-zA-Z0-9.\-]{1,253}$')

@app.get('/health')
def health(): return jsonify(status='ok', tool='nmap')

@app.post('/scan')
def scan():
    data   = request.get_json(force=True) or {}
    target = (data.get('target') or '').strip()
    if not target or not SAFE_TARGET.match(target):
        return jsonify(error='Invalid target'), 400
    try:
        out = subprocess.run(
            ['nmap', '-oX', '-', '-sV', '-T4', target],
            capture_output=True, text=True, timeout=300
        )
        return jsonify(success=True, stdout=out.stdout[:50000])
    except subprocess.TimeoutExpired:
        return jsonify(success=False, error='Scan timed out'), 504
    except Exception as e:
        return jsonify(success=False, error=str(e)), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=9101, debug=False)
