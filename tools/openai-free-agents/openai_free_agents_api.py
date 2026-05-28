import time
import uuid

from flask import Flask, jsonify, request


app = Flask(__name__)


AGENTS = [
    {"id": "triage-agent", "name": "Triage Agent", "role": "Rank SAST, DAST, SCA, and OASM findings."},
    {"id": "remediation-agent", "name": "Remediation Agent", "role": "Draft safe fix guidance and retest steps."},
    {"id": "report-agent", "name": "Report Agent", "role": "Create client-ready executive and technical summaries."},
]


def build_reply(prompt: str) -> str:
    text = prompt.lower()
    if "dast" in text or "zap" in text:
        return "DAST triage: confirm scope, group duplicate alerts, and prioritize server-side validation findings before low-signal headers."
    if "sast" in text or "sonar" in text or "semgrep" in text:
        return "SAST triage: map findings to CWE, verify reachability, and generate minimal remediation notes for code owners."
    if "sca" in text or "dependency" in text or "trivy" in text:
        return "SCA triage: prioritize reachable critical packages, validate fixed versions, and include SBOM evidence in the client report."
    if "attack surface" in text or "oasm" in text:
        return "OASM triage: mark public assets, check ownership, and route newly discovered services into approved DAST scope."
    return "AutoSecForge agents are ready: triage findings, draft remediation, and produce client-ready SAST, DAST, SCA, and OASM reporting."


@app.get("/health")
def health():
    return jsonify(status="ok", service="openai-free-agents", agents=len(AGENTS))


@app.get("/agents")
def agents():
    return jsonify(service="OpenAI-compatible Free Agents", agents=AGENTS)


@app.post("/v1/chat/completions")
def chat_completions():
    payload = request.get_json(silent=True) or {}
    messages = payload.get("messages", [])
    prompt = " ".join(str(message.get("content", "")) for message in messages if isinstance(message, dict))
    reply = build_reply(prompt)
    return jsonify(
        id=f"chatcmpl-{uuid.uuid4().hex[:12]}",
        object="chat.completion",
        created=int(time.time()),
        model=payload.get("model", "autosecforge-free-agents"),
        choices=[{"index": 0, "message": {"role": "assistant", "content": reply}, "finish_reason": "stop"}],
        usage={
            "prompt_tokens": max(1, len(prompt.split())),
            "completion_tokens": len(reply.split()),
            "total_tokens": max(1, len(prompt.split())) + len(reply.split()),
        },
    )


@app.get("/summary")
def summary():
    return jsonify(service="OpenAI-compatible Free Agents", model="autosecforge-free-agents", endpoint="/v1/chat/completions", agents=AGENTS)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=6400)
