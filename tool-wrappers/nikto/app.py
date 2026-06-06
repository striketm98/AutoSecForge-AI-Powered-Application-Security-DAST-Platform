import subprocess, re
from flask import Flask, request, jsonify
app = Flask(__name__)

URL_RE = re.compile(r'^https?://[a-zA-Z0-9.\-]+(:\d{1,5})?(/[^\s]*)?$')

@app.get('/health')
def health(): return jsonify(status='ok', tool='nikto')

@app.post('/scan')
def scan():
    data = request.get_json(force=True) or {}
    url  = (data.get('url') or '').strip()
    if not url or not URL_RE.match(url):
        return jsonify(error='Invalid URL'), 400
    try:
        out = subprocess.run(
            ['nikto', '-h', url, '-Format', 'json'],
            capture_output=True, text=True, timeout=600
        )
        return jsonify(success=True, stdout=out.stdout[:50000])
    except subprocess.TimeoutExpired:
        return jsonify(success=False, error='Scan timed out'), 504
    except Exception as e:
        return jsonify(success=False, error=str(e)), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=9102, debug=False)
