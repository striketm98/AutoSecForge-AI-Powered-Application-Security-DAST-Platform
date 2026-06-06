#!/usr/bin/env python3
"""
AutoSecForge AI Agent – Keyword Routing (no external LLM)
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import re
import uuid
from datetime import datetime

app = Flask(__name__)
CORS(app)

# Keyword routing tables
ROUTES = {
    r'\b(dast|zap|dynamic)\b': 'dast_triage',
    r'\b(sast|sonar|semgrep|static)\b': 'sast_triage',
    r'\b(sca|dependency|trivy|sbom)\b': 'sca_triage',
    r'\b(attack.surface|oasm|asset)\b': 'oasm_triage',
}

RESPONSES = {
    'dast_triage': (
        "DAST Triage Agent: Prioritising findings from OWASP ZAP. "
        "Grouping duplicate alerts by URL pattern. Flagging server-side "
        "validation failures as high-confidence. Recommend manual review "
        "of authentication bypass findings before automated remediation."
    ),
    'sast_triage': (
        "SAST Triage Agent: Analysing SonarQube/Semgrep results. "
        "Mapping findings to CWE IDs. Verifying reachability of flagged code paths. "
        "Generating code-owner remediation notes with file and line references."
    ),
    'sca_triage': (
        "SCA Triage Agent: Scanning dependency tree for CVEs via Trivy. "
        "Identifying reachable critical packages. Suggesting fixed versions. "
        "Generating SBOM evidence artefact for compliance reporting."
    ),
    'oasm_triage': (
        "OASM Triage Agent: Reviewing attack surface inventory. "
        "Marking public-facing assets, verifying ownership, routing confirmed "
        "live targets to DAST scan queue. Flagging shadow IT exposures."
    ),
    'default': (
        "AutoSecForge AI — Full Suite Agent. Include keywords such as "
        "'dast', 'sast', 'sca', or 'attack surface' to route to a specialist agent. "
        "I can assist with finding triage, remediation guidance, and report generation."
    ),
}

def route_agent(content: str) -> str:
    content_lower = content.lower()
    for pattern, agent in ROUTES.items():
        if re.search(pattern, content_lower):
            return agent
    return 'default'

@app.route('/health', methods=['GET'])
def health():
    return jsonify(status='ok', service='openai-free-agents', version='12.0')

@app.route('/v1/chat/completions', methods=['POST'])
def chat_completions():
    data = request.get_json(silent=True) or {}
    messages = data.get('messages', [])
    last_user = next((m['content'] for m in reversed(messages) if m.get('role') == 'user'), '')

    agent = route_agent(last_user)
    reply = RESPONSES[agent]

    return jsonify({
        'id': f'chatcmpl-{uuid.uuid4().hex[:12]}',
        'object': 'chat.completion',
        'created': int(datetime.now().timestamp()),
        'model': 'autosecforge-local',
        'choices': [{
            'index': 0,
            'message': {'role': 'assistant', 'content': reply},
            'finish_reason': 'stop',
        }],
        'usage': {'prompt_tokens': 0, 'completion_tokens': 0, 'total_tokens': 0},
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=6400, debug=False)
