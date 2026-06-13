#!/usr/bin/env python3
"""
AutoSecForge AI Agent – Ollama-backed LLM with OpenAI-compatible API
"""

import os
import re
import uuid
import json
import requests
from datetime import datetime
from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

OLLAMA_BASE_URL = os.environ.get('OLLAMA_URL', 'http://ollama:11434')
OLLAMA_MODEL    = os.environ.get('OLLAMA_MODEL', 'llama3')

SECURITY_SYSTEM_PROMPT = """You are AutoSecForge, an expert penetration testing and security analysis AI.
Your role is to:
- Triage and prioritise security findings from DAST/SAST/SCA/OASM scans
- Analyse nmap, nikto, sqlmap, and ZAP results
- Map findings to CVEs, CWEs, and OWASP Top 10
- Provide clear remediation guidance with severity ratings (Critical/High/Medium/Low/Info)
- Generate concise executive summaries and technical detail sections
- Identify attack surface exposures and recommend countermeasures

Always structure your responses with:
1. Executive Summary (2-3 sentences)
2. Key Findings (bullet list with severity)
3. Recommended Actions (prioritised)

Be concise, technical, and actionable.

After the prose report, you MUST append a machine-readable findings block in
EXACTLY this format (a JSON array between the markers, nothing else between them):

<<<FINDINGS_JSON>>>
[
  {
    "title": "short finding title",
    "severity": "critical|high|medium|low|info",
    "cvss_score": 0.0,
    "cwe_id": "CWE-79 or empty",
    "cve_id": "CVE-2021-XXXX or empty",
    "affected_url": "host/path/port or empty",
    "description": "what it is and why it matters",
    "remediation": "how to fix it"
  }
]
<<<END_FINDINGS>>>

If the scan output shows no issues, return an empty array: []. Use only the five
severity values listed. Never invent CVEs — leave cve_id empty if unsure."""


def extract_findings(text: str):
    """
    Pull the structured findings JSON out of the model output.
    Returns (prose_without_block, findings_list). Defensive: small models often
    emit slightly malformed JSON, so we try hard and degrade to [] on failure.
    """
    if not text:
        return text, []

    block = None
    m = re.search(r'<<<FINDINGS_JSON>>>(.*?)<<<END_FINDINGS>>>', text, re.DOTALL)
    if m:
        block = m.group(1)
        prose = text[:m.start()].rstrip()
    else:
        # Fallback: grab the last JSON array in the text
        arr = re.findall(r'\[\s*\{.*?\}\s*\]', text, re.DOTALL)
        if arr:
            block = arr[-1]
            prose = text.replace(block, '').rstrip()
        else:
            return text.rstrip(), []

    findings = []
    try:
        parsed = json.loads(block.strip())
        if isinstance(parsed, list):
            valid_sev = {'critical', 'high', 'medium', 'low', 'info'}
            for f in parsed:
                if not isinstance(f, dict) or not f.get('title'):
                    continue
                sev = str(f.get('severity', 'medium')).strip().lower()
                if sev not in valid_sev:
                    sev = 'medium'
                try:
                    cvss = float(f.get('cvss_score') or 0) or None
                except (TypeError, ValueError):
                    cvss = None
                findings.append({
                    'title':        str(f.get('title', ''))[:500],
                    'severity':     sev,
                    'cvss_score':   cvss,
                    'cwe_id':       str(f.get('cwe_id', ''))[:20],
                    'cve_id':       str(f.get('cve_id', ''))[:30],
                    'affected_url': str(f.get('affected_url', ''))[:1000],
                    'description':  str(f.get('description', '')),
                    'remediation':  str(f.get('remediation', '')),
                })
    except (ValueError, TypeError):
        # Couldn't parse — keep the prose, drop the block silently.
        pass

    return prose, findings


def ollama_chat(messages: list, stream: bool = False) -> dict:
    """Call Ollama /api/chat and return a response dict."""
    payload = {
        'model': OLLAMA_MODEL,
        'messages': messages,
        'stream': False,
        'options': {'temperature': 0.2, 'num_predict': 2048},
    }
    try:
        resp = requests.post(
            f'{OLLAMA_BASE_URL}/api/chat',
            json=payload,
            timeout=120,
        )
        resp.raise_for_status()
        data = resp.json()
        return {'ok': True, 'content': data['message']['content']}
    except requests.exceptions.ConnectionError:
        return {'ok': False, 'content': (
            'Ollama is not reachable. Ensure the Ollama service is running '
            f'and the model "{OLLAMA_MODEL}" is pulled.'
        )}
    except Exception as e:
        return {'ok': False, 'content': f'Ollama error: {str(e)}'}


@app.route('/health', methods=['GET'])
def health():
    try:
        r = requests.get(f'{OLLAMA_BASE_URL}/api/tags', timeout=5)
        ollama_ok = r.status_code == 200
        models = [m['name'] for m in r.json().get('models', [])] if ollama_ok else []
    except Exception:
        ollama_ok = False
        models = []
    return jsonify(
        status='ok',
        service='autosecforge-ai-agent',
        version='12.1',
        ollama=ollama_ok,
        active_model=OLLAMA_MODEL,
        available_models=models,
    )


@app.route('/v1/chat/completions', methods=['POST'])
def chat_completions():
    """OpenAI-compatible endpoint backed by Ollama."""
    data = request.get_json(silent=True) or {}
    messages = data.get('messages', [])

    # Prepend system prompt if not already set
    if not any(m.get('role') == 'system' for m in messages):
        messages = [{'role': 'system', 'content': SECURITY_SYSTEM_PROMPT}] + messages

    result = ollama_chat(messages)
    reply  = result['content']

    return jsonify({
        'id': f'chatcmpl-{uuid.uuid4().hex[:12]}',
        'object': 'chat.completion',
        'created': int(datetime.now().timestamp()),
        'model': OLLAMA_MODEL,
        'choices': [{
            'index': 0,
            'message': {'role': 'assistant', 'content': reply},
            'finish_reason': 'stop',
        }],
        'usage': {'prompt_tokens': 0, 'completion_tokens': 0, 'total_tokens': 0},
    })


@app.route('/v1/security-review', methods=['POST'])
def security_review():
    """
    Dedicated security review endpoint.
    Accepts raw scan output and returns a structured triage report.
    Body: { "target": str, "scan_type": str, "raw_output": str }
    """
    data = request.get_json(silent=True) or {}
    target     = data.get('target', 'unknown')
    scan_type  = data.get('scan_type', 'generic')
    raw_output = data.get('raw_output', '')

    if not raw_output:
        return jsonify(error='raw_output is required'), 400

    user_msg = (
        f"Perform a security review for target: {target}\n"
        f"Scan type: {scan_type}\n\n"
        f"--- RAW SCAN OUTPUT ---\n{raw_output[:12000]}\n--- END ---\n\n"
        "Provide a structured security triage report."
    )

    messages = [
        {'role': 'system', 'content': SECURITY_SYSTEM_PROMPT},
        {'role': 'user',   'content': user_msg},
    ]
    result = ollama_chat(messages)

    # Split the prose triage from the structured findings block.
    prose, findings = extract_findings(result['content']) if result['ok'] else (result['content'], [])

    return jsonify(
        target=target,
        scan_type=scan_type,
        model=OLLAMA_MODEL,
        analysis=prose,
        findings=findings,
        ok=result['ok'],
        timestamp=datetime.utcnow().isoformat() + 'Z',
    )


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=6400, debug=False)
