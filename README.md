<div align="center">
  <img src="https://img.shields.io/badge/AutoSecForge-Pro-00A86B?style=for-the-badge&logo=datadog&logoColor=white" alt="AutoSecForge Pro">
  <br>
  <img src="https://img.shields.io/badge/Version-2.2.0-blueviolet?style=for-the-badge&logo=gitbook&logoColor=white" alt="Version">
  <img src="https://img.shields.io/badge/License-Enterprise-7A288A?style=for-the-badge&logo=law&logoColor=white" alt="License">
  <img src="https://img.shields.io/badge/Kali%20Linux-557C94?style=for-the-badge&logo=kalilinux&logoColor=white" alt="Kali">
  <img src="https://img.shields.io/badge/WSL-0A5C89?style=for-the-badge&logo=windows&logoColor=white" alt="WSL">
  <img src="https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/MCP%20HackStrike-FF4500?style=for-the-badge&logo=chainlink&logoColor=white" alt="MCP">
  <img src="https://img.shields.io/badge/AI%20Agents-00A67E?style=for-the-badge&logo=openai&logoColor=white" alt="AI Agents">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-8.4-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <br>
  <img src="https://img.shields.io/github/stars/striketm98/AutoSecForge-AI-Powered-Application-Security-DAST-Platform?style=social" alt="GitHub stars">
  <img src="https://img.shields.io/github/forks/striketm98/AutoSecForge-AI-Powered-Application-Security-DAST-Platform?style=social" alt="GitHub forks">
  <img src="https://img.shields.io/github/last-commit/striketm98/AutoSecForge-AI-Powered-Application-Security-DAST-Platform" alt="Last commit">
</div>

# 🛡️ AutoSecForge Pro

**The unified application security dashboard for Kali Linux on WSL2** – integrating SAST, DAST, SCA, mobile security, attack surface management (OASM), MCP-based connector routing, and local OpenAI‑compatible AI agents – all inside a single Docker workspace.

---

## 📋 Table of Contents

- [Key Features](#-key-features)
- [Architecture Diagram](#-architecture-diagram)
- [Workflow Diagram](#-workflow-diagram)
- [Quick Start on WSL Kali](#-quick-start-on-wsl-kali)
- [Service Endpoints](#-service-endpoints)
- [Core Workflow](#-core-workflow)
- [MCP HackStrike Connector Fabric](#-mcp-hackstrike-connector-fabric)
- [Local AI Agents (OpenAI‑compatible)](#-local-ai-agents-openai‑compatible)
- [Reporting & Deliverables](#-reporting--deliverables)
- [Development & Maintenance](#-development--maintenance)
- [Security Hardening](#-security-hardening)
- [License & Contact](#-license--contact)

---

## 🚀 Key Features

| Category | Capabilities |
|----------|--------------|
| **AppSec Suite** | SonarQube (SAST), OWASP ZAP (DAST), Dependency‑Check (SCA), MobSF (mobile) |
| **Attack Surface** | Open ASM asset inventory, exposure history, continuous discovery |
| **MCP Fabric** | JSON‑RPC connector mesh: SonarQube, Semgrep, ZAP, Trivy, Dependency‑Check, Open ASM |
| **AI Agents** | Local OpenAI‑compatible `/v1/chat/completions` for triage, remediation, report drafting |
| **Client Management** | Multi‑tenant onboarding, scan job tracking, audit logs |
| **Reporting** | Executive summaries, technical findings, PDF/HTML/Word/Excel/CSV/JSON exports |
---
## 🏗️ Architecture Diagram

```mermaid
flowchart TB
    subgraph Dashboard["Dashboard (PHP/MySQL)"]
        UI["Web UI (Port 8080)"]
        DB["(MySQL tenants/scans/findings)"]
    end

    subgraph MCP["MCP HackStrike (Port 6300)"]
        Router["JSON-RPC Router"]
        Connectors["Connector Catalog"]
    end

    subgraph Tools["Security Tools"]
        Sonar["SonarQube SAST (Port 9000)"]
        ZAP["OWASP ZAP DAST (Port 8090)"]
        DepCheck["Dependency-Check SCA (Port 8100)"]
        MobSF["MobSF Mobile (Port 8000)"]
        ASM["Open ASM (Port 8200)"]
    end

    subgraph AI["OpenAI‑compatible Agents (Port 6400)"]
        Triage["Triage Agent"]
        Remediate["Remediation Agent"]
        Report["Report Agent"]
    end

    UI --> MCP
    UI --> AI
    MCP --> Tools
    AI --> Triage
    AI --> Remediate
    AI --> Report

    style Dashboard fill:#1a1a2e,stroke:#00A86B,stroke-width:2px
    style MCP fill:#0f3460,stroke:#FF4500,stroke-width:2px
    style Tools fill:#16213e,stroke:#e94560,stroke-width:2px
    style AI fill:#533483,stroke:#00A86B,stroke-width:2px
```
---
## ⚡ Quick Start on WSL Kali

### 1. Install WSL2 and Kali
```bash
wsl --install -d kali-linux
```

### 2. Inside Kali, install Docker
```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker "$USER"
newgrp docker
sudo apt update && sudo apt install -y docker-compose-plugin
```

### 3. Clone and start AutoSecForge Pro
```bash
git clone https://github.com/striketm98/AutoSecForge-AI-Powered-Application-Security-DAST-Platform.git
cd AutoSecForge-AI-Powered-Application-Security-DAST-Platform
docker compose up -d --build
docker compose ps
```

### 4. Access the dashboard
Open your browser at: [http://localhost:8080](http://localhost:8080)

**Default credentials:**  
`admin@cyber-security.local` / `ChangeMe123!`

> ⚠️ **Important:** Change the default password immediately in production environments.

---

## 🌐 Service Endpoints

| Service | URL | Purpose |
|---------|-----|---------|
| 🖥️ Dashboard (PHP) | `http://localhost:8080` | Main UI, scan launcher, reporting |
| 🧬 SonarQube (SAST) | `http://localhost:9000` | Code quality & security hotspots |
| 🔍 OWASP ZAP (DAST) | `http://localhost:8090` | Dynamic application scanning |
| 📦 Dependency‑Check (SCA) | `http://localhost:8100` | Third‑party library vulnerabilities |
| 📱 MobSF (Mobile) | `http://localhost:8000` | APK/IPA security analysis |
| 🌍 Open ASM (OASM) | `http://localhost:8200` | Attack surface inventory |
| 🔌 MCP HackStrike | `http://localhost:6300` | JSON‑RPC connector mesh |
| 🤖 OpenAI‑compatible Agents | `http://localhost:6400` | Local AI for triage/remediation/reporting |

---

## 🔄 Core Workflow

1. **Onboard a client** – Create a new tenant in the dashboard.
2. **Launch AppSec suite** – One‑click trigger for SAST, SCA, DAST, mobile, OASM, and AI agents.
3. **MCP HackStrike routes** – The connector fabric dispatches requests to each security tool.
4. **Collect findings** – Scan results are imported, normalised, and stored.
5. **AI enrichment** – Agents generate triage notes, remediation suggestions, and report summaries.
6. **Review & export** – Analysts validate findings, add comments, and export PDF/Word/Excel reports.

---

## 🔌 MCP HackStrike Connector Fabric

The **MCP HackStrike** service implements a lightweight JSON‑RPC mesh over HTTP.  
Endpoints:

- `GET /health` – service health
- `GET /connectors` – list available connectors
- `GET /summary` – brief status
- `POST /rpc` – execute a connector call

### Enabled connectors (configurable)

| Connector | Type | Purpose |
|-----------|------|---------|
| `sonarqube_mcp` | SAST | Pull project quality gates and issues |
| `semgrep_mcp` | SAST | Lightweight static analysis |
| `zap_mcp` | DAST | Trigger active scans, retrieve alerts |
| `trivy_mcp` | Container | Scan image vulnerabilities |
| `dependency-check_mcp` | SCA | Check dependencies for CVEs |
| `openasm_mcp` | OASM | Asset discovery and exposure tracking |

Each connector can be replaced with a production‑grade implementation later without changing the dashboard integration.

```mermaid
flowchart LR
    Dashboard[Dashboard] -- RPC --> MCP[MCP HackStrike]
    MCP -- JSON-RPC --> Sonar[SonarQube]
    MCP --> ZAP[ZAP]
    MCP --> DepCheck[Dependency-Check]
    MCP --> MobSF[MobSF]
    MCP --> ASM[Open ASM]
```

---

## 🤖 Local AI Agents (OpenAI‑compatible)

The agent service mimics the OpenAI Chat Completions API – use it with any OpenAI SDK by changing the `base_url`.  
Endpoints:

- `GET /health`
- `GET /agents`
- `GET /summary`
- `POST /v1/chat/completions`

### Included placeholder agents

| Agent | Function |
|-------|----------|
| **Triage Agent** | Ranks findings by severity and relevance |
| **Remediation Agent** | Generates fix suggestions (code snippets, links) |
| **Report Agent** | Writes executive summaries and technical descriptions |

You can later replace the backend with **Ollama**, **vLLM**, **OpenChat**, or a real OpenAI endpoint while keeping the same API shape.

```mermaid
flowchart TD
    Finding[Finding] --> Triage[Triage Agent]
    Triage --> Remediation[Remediation Agent]
    Remediation --> Report[Report Agent]
    Report --> User[Analyst Review]
```

---

## 📊 Reporting & Deliverables

The dashboard supports:

- **Executive summary** – automatically generated by the Report Agent.
- **Technical findings table** – with CWE mapping, severity, remediation notes.
- **Export formats**: PDF (printable HTML), Word, Excel, CSV, JSON.
- **Evidence attachments** – scan logs, screenshots, raw JSON.

Reports can be generated per client and saved to the local filesystem or downloaded directly.

---

## 🛠️ Development & Maintenance

### View logs
```bash
docker compose logs -f app
docker compose logs -f mcp-hackstrike
docker compose logs -f openai-free-agents
```

### Restart a specific service
```bash
docker compose restart app
```

### Stop everything
```bash
docker compose down
```

### Database schema
The initial schema is located at `database/schema.sql`. It creates tables for clients, scans, findings, and reports.

### Customisation
- PHP backend: `public/` and `src/`
- MCP HackStrike source: `mcp-hackstrike/`
- AI agents source: `openai-free-agents/`
- Docker Compose configuration: `docker-compose.yml`

---

## 🔐 Security Hardening

For production deployments, **always** apply these measures:

1. **Change all default passwords** – dashboard admin, SonarQube, ZAP, MySQL, MobSF.
2. **Use strong secrets** – generate passwords with `openssl rand -base64 32`.
3. **Enable HTTPS** – place behind a reverse proxy (Traefik, Nginx) with TLS certificates.
4. **Isolate the network** – do not expose internal ports to the internet.
5. **Regular updates** – `docker compose pull && docker compose up -d`
6. **Audit logs** – monitor failed login attempts and API calls.

> See [SECURITY.md](SECURITY.md) for the vulnerability disclosure policy.

---

## 📄 License & Contact

AutoSecForge Pro is released under a **commercial Enterprise License**.  
See [LICENSE.md](LICENSE.md) for full terms.

**Contact:** `mazumdertamal81@gmail.com`  
**Documentation:** [AutoSecForge Pro Docs](https://tamalkantimazumder.netlify.app/AutoSecForgePRo)

---

<div align="center">
  <hr width="60%">
  <strong>Built with ❤️ for security professionals.</strong><br>
  <sub>AutoSecForge Pro – Unified, AI‑ready, and Docker‑native.</sub>
  <br><br>
  <a href="#autosecforge-pro">⬆ Back to Top</a>
</div>
