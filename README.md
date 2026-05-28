# AutoSecForge Pro

AutoSecForge Pro is a Docker-first application security dashboard for Kali Linux on WSL2. It brings SAST, DAST, SCA, mobile review, open attack-surface management, MCP HackStrike connector routing, and OpenAI-compatible local agents into one PHP/MySQL workspace.

## What Runs

| Layer | Service | Purpose |
| --- | --- | --- |
| Frontend/backend | `app` | PHP 8.3 Apache dashboard, scan launcher, reporting, import, audit, client onboarding |
| Database | `db` | MySQL 8.4 seed data and dashboard state |
| SAST | `sonarqube` | Source quality and security hotspot tracking |
| DAST | `zap` | OWASP ZAP daemon for dynamic scans |
| SCA | `dependency-check` | Dependency vulnerability review |
| Mobile | `mobsf` | APK/mobile security review |
| Pentest helper | `pentest-python` | Safe authorized validation playbooks |
| OASM | `oasm` | Open attack-surface inventory and exposure summary |
| MCP fabric | `mcp-hackstrike` | JSON-RPC connector mesh for SAST, DAST, SCA, container, and OASM routing |
| AI agents | `openai-free-agents` | Local OpenAI-compatible `/v1/chat/completions` endpoint for triage, remediation, and report drafting |

## Quick Start On WSL Kali + Docker

```bash
wsl --install -d kali-linux
```

Inside Kali:

```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker "$USER"
newgrp docker
sudo apt update && sudo apt install -y docker-compose-plugin
```

Start the platform:

```bash
docker compose up -d --build
docker compose ps
```

Open the dashboard:

- Dashboard: `http://localhost:8080`
- Admin login: `admin@cyber-security.local` / `ChangeMe123!`
- SonarQube: `http://localhost:9000`
- ZAP API: `http://localhost:8090`
- MobSF: `http://localhost:8000`
- MCP HackStrike: `http://localhost:6300`
- OpenAI-compatible agents: `http://localhost:6400/v1/chat/completions`

Change default passwords before using this beyond a local demo.

## Core Workflow

1. Onboard a client in the dashboard.
2. Launch the AppSec suite from the dashboard.
3. MCP HackStrike plans the connector route.
4. SAST, SCA, DAST, mobile, pentest, and OASM services contribute scan state and evidence.
5. OpenAI-compatible free agents generate safe triage, remediation, and report notes.
6. Export executive and technical deliverables as PDF-ready HTML, Word, Excel, CSV, or JSON.

## MCP HackStrike

The local MCP HackStrike service exposes:

- `GET /health`
- `GET /connectors`
- `GET /summary`
- `POST /rpc`

Its connector catalog currently includes SonarQube MCP, Semgrep MCP, OWASP ZAP MCP, Trivy MCP, Dependency-Check MCP, and Open ASM MCP. It is intentionally lightweight so you can replace each connector with a production adapter later.

## OpenAI-Compatible Free Agents

The local agent service exposes:

- `GET /health`
- `GET /agents`
- `GET /summary`
- `POST /v1/chat/completions`

It follows the OpenAI chat completions response shape and works without a paid API key. The current agents are rule-based placeholders for local development:

- Triage Agent
- Remediation Agent
- Report Agent

You can later point the same integration profile to OpenAI, Ollama, vLLM, OpenChat, or another OpenAI-compatible model server.

## SAST, DAST, SCA, And Reporting

The dashboard supports:

- One-click suite launch for MCP routing, SAST, SCA, DAST, MobSF, and AI agents.
- Scan job tracking with retry, completion, and failure controls.
- JSON import for scanner findings.
- CWE mapping, false-positive review, analyst notes, and AI remediation notes.
- Open ASM asset inventory and history.
- Client-ready report pages and exports.

## Development Notes

Useful commands:

```bash
docker compose logs -f app
docker compose logs -f mcp-hackstrike
docker compose logs -f openai-free-agents
docker compose down
```

The seed schema lives in `database/schema.sql`. The PHP app lives in `public/` and shared helpers live in `src/`.

## License

See `LICENSE.md`.
