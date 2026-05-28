from flask import Flask, jsonify, request


app = Flask(__name__)


CONNECTORS = [
    {"id": "sonarqube", "name": "SonarQube MCP", "type": "sast", "endpoint": "http://sonarqube:9000", "status": "ready"},
    {"id": "semgrep", "name": "Semgrep MCP", "type": "sast", "endpoint": "cli://semgrep", "status": "available"},
    {"id": "zap", "name": "OWASP ZAP MCP", "type": "dast", "endpoint": "http://zap:8090", "status": "ready"},
    {"id": "trivy", "name": "Trivy MCP", "type": "container", "endpoint": "cli://trivy", "status": "available"},
    {"id": "dependency-check", "name": "Dependency-Check MCP", "type": "sca", "endpoint": "container://dependency-check", "status": "ready"},
    {"id": "oasm", "name": "Open ASM MCP", "type": "attack-surface", "endpoint": "http://oasm:6200", "status": "ready"},
]


@app.get("/health")
def health():
    return jsonify(status="ok", service="mcp-hackstrike", connectors=len(CONNECTORS))


@app.get("/connectors")
def connectors():
    return jsonify(service="MCP HackStrike", connectors=CONNECTORS)


@app.post("/rpc")
def rpc():
    payload = request.get_json(silent=True) or {}
    params = payload.get("params", {})
    scan_kind = params.get("scan_kind", "suite")
    selected = [
        connector
        for connector in CONNECTORS
        if scan_kind == "suite" or connector["type"] in {scan_kind, "attack-surface"}
    ]
    return jsonify(
        jsonrpc="2.0",
        id=payload.get("id", "local"),
        result={
            "method": payload.get("method", "scan.plan"),
            "status": "accepted",
            "connectors": selected,
            "routing": "sast+dast+sca+oasm",
        },
    )


@app.get("/summary")
def summary():
    return jsonify(
        service="MCP HackStrike",
        fabric="JSON-RPC connector mesh",
        coverage=["sast", "dast", "sca", "container", "attack-surface"],
        connectors=CONNECTORS,
    )


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=6300)
