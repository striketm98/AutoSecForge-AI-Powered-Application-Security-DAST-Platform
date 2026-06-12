# AutoSecForge Pro v12.1

**AI-Powered Security Orchestration & Reporting Platform**

AutoSecForge is a self-hosted security operations dashboard that orchestrates industry-standard security tools (nmap, nikto, sqlmap, OWASP ZAP, SonarQube, MobSF, Trivy), runs AI-assisted triage entirely **locally** via [Ollama](https://ollama.com) — no cloud API keys, no data leaves your machine — and presents everything through a professional AdminLTE 3.2 dashboard with role-based access control, audit logging, and exportable reports.

> ⚠️ **Authorized use only.** AutoSecForge automates active security scanning. Only scan targets you own or have explicit written permission to test.

---

## Table of Contents

1. [Key Features](#key-features)
2. [Architecture](#architecture)
3. [Service & Port Reference](#service--port-reference)
4. [Prerequisites](#prerequisites)
5. [Installation from Scratch (Windows → WSL2 → Kali → Docker)](#installation-from-scratch)
6. [Ollama Local AI Setup](#ollama-local-ai-setup)
7. [First Login & RBAC](#first-login--rbac)
8. [Running a Security Review](#running-a-security-review)
9. [Reporting & Export](#reporting--export)
10. [Configuration Reference](#configuration-reference)
11. [Troubleshooting](#troubleshooting)
12. [Security Hardening Notes](#security-hardening-notes)
13. [Project Structure](#project-structure)

---

## Key Features

| Area | Capability |
|---|---|
| **AI Triage (local)** | Ollama-backed LLM analyzes raw scan output and produces an executive summary, severity-ranked findings, and remediation steps. OpenAI-compatible API (`/v1/chat/completions`) for tool interop. |
| **Orchestrated Scanning** | One click runs nmap (network), nikto (DAST), and sqlmap (SQLi) in isolated containers, aggregates output, and feeds it to the AI agent. |
| **Pro Dashboard** | AdminLTE 3.2 dark-indigo UI: live KPI cards, 7-day scan trend chart, scan status donut, real-time tool health grid, recent activity feed. |
| **RBAC** | Six roles (admin, manager, analyst, client, auditor, executive) with role-gated navigation and data scoping. |
| **Reporting** | Per-scan reports with AI analysis + raw evidence, preview modal, one-click `.txt` export; audit log of all actions. |
| **Tool Suite** | OWASP ZAP, SonarQube CE, MobSF, Trivy server, SQLMap API, OASM attack-surface service — all pre-wired in compose. |
| **Safety Rails** | SSRF guards (private-IP targets rejected at both PHP and MCP layers), rate limiting, helmet headers, hardened `.htaccess`, allow-listed scan flags. |
| **WSL2/Kali Ready** | `host.docker.internal:host-gateway` mapping; works identically on Docker Desktop, WSL2 Docker Engine, and Kali-in-WSL. |

---

## Architecture

```
┌────────────────────────────────────────────────────────────────────┐
│  Browser → https://autosecforge.com   (HTTPS only · IP access 403) │
└───────────────┬────────────────────────────────────────────────────┘
                │
        ┌───────▼────────┐        ┌──────────────┐
        │  app (PHP 8.3  │───────▶│  db (MySQL 8)│  users / scan_jobs /
        │  Apache +      │        └──────────────┘  findings / audit_log
        │  AdminLTE 3.2) │
        └───────┬────────┘
                │ POST /scan/security-review
        ┌───────▼─────────────┐
        │  mcp-router :6300   │── docker exec ──▶ nmap / nikto / sqlmap
        │  (Node/Express)     │                   containers
        └───────┬─────────────┘
                │ POST /v1/security-review
        ┌───────▼─────────────┐        ┌─────────────────┐
        │  ai-agent :6400     │───────▶│  ollama :11434  │
        │  (Flask, OpenAI-    │        │  (local LLM)    │
        │  compatible API)    │        └─────────────────┘
        └─────────────────────┘

  Internal-only services (no host port): ZAP · SonarQube · MobSF
                 Trivy · OASM · SQLMap · nmap · nikto
```

**Security-review data flow:**
`scan_trigger.php` → MCP router validates target (blocks private IPs) → executes selected tools sequentially in their containers → raw output aggregated → AI agent builds a structured triage prompt → Ollama returns analysis → result persisted to `scan_jobs` → rendered in UI / exportable as report.

---

## Access Model & Ports

AutoSecForge is **locked down by default**:

- **Only the dashboard is reachable from the host**, and only over **HTTPS via `https://autosecforge.com`**.
- The app's ports are **bound to `127.0.0.1`** — it is *not* reachable by raw IP or from the LAN.
- Apache **rejects any request whose `Host` header is not `autosecforge.com`** (raw-IP access returns `403 Forbidden`), and plain HTTP is 301-redirected to HTTPS.
- **Every other service has no host port at all** — MySQL, Ollama, the AI agent, the MCP router, and all scanner tools are exposed *only* on the internal `asf-net` Docker network for service-to-service traffic.

| Service | Container | Host-reachable? | Internal port | Purpose |
|---|---|---|---|---|
| Dashboard (PHP/Apache) | `autosecforge-app` | ✅ `127.0.0.1:443` (HTTPS) + `:80` redirect | 443 / 80 | Web UI — domain only |
| MySQL 8.4 | `autosecforge-db` | ❌ internal only | 3306 | Persistence |
| Ollama | `autosecforge-ollama` | ❌ internal only | 11434 | Local LLM inference |
| AI Agent (Flask) | `autosecforge-ai-agent` | ❌ internal only | 6400 | Triage + OpenAI-compatible API |
| MCP Router (Node) | `autosecforge-mcp-router` | ❌ internal only | 6300 | Scan orchestration |
| nmap | `autosecforge-nmap` | ❌ internal only | — | Network scanning (exec target) |
| nikto | `autosecforge-nikto` | ❌ internal only | — | Web scanning (exec target) |
| SQLMap | `autosecforge-sqlmap` | ❌ internal only | — | SQL injection (exec target) |
| OASM | `autosecforge-oasm` | ❌ internal only | 6200 | Attack-surface mapping |
| OWASP ZAP | `autosecforge-zap` | ❌ internal only | 8090 | DAST daemon |
| SonarQube CE | `autosecforge-sonarqube` | ❌ internal only | 9000 | SAST |
| MobSF | `autosecforge-mobsf` | ❌ internal only | 8000 | Mobile app security |
| Trivy server | `autosecforge-trivy` | ❌ internal only | 8081 | Container/SCA scanning |

> **Need a tool's web UI (e.g. SonarQube) during setup?** Don't publish its port. Tunnel it locally instead:
> `docker compose exec sonarqube true` then reach it from another container, or temporarily run
> `ssh -L 9000:localhost:9000` style forwarding — never add a public `ports:` mapping in production.

---

## Prerequisites

- **Windows 10 21H2+ / Windows 11** with virtualization enabled in BIOS, **or** any Linux host with Docker Engine 24+.
- **8 GB RAM minimum** (16 GB recommended — Ollama + SonarQube are memory-hungry).
- **~20 GB free disk** (images + LLM model weights).
- Optional: NVIDIA GPU for faster Ollama inference (CUDA drivers + nvidia-container-toolkit).

---

## Installation from Scratch

### Step 1 — Install WSL2 (Windows host)

Open **PowerShell as Administrator**:

```powershell
wsl --install --no-distribution
# Reboot when prompted
wsl --set-default-version 2
```

### Step 2 — Install Kali Linux on WSL2

```powershell
wsl --install -d kali-linux
```

Launch Kali, create your UNIX user, then update:

```bash
sudo apt update && sudo apt full-upgrade -y
```

### Step 3 — Install Docker Engine inside Kali

```bash
# Dependencies
sudo apt install -y ca-certificates curl gnupg

# Docker repository (Kali tracks Debian — use the Debian bookworm repo)
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/debian bookworm stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Run Docker without sudo
sudo usermod -aG docker $USER
newgrp docker

# Start the daemon (WSL2 has no systemd by default; enable it)
sudo tee -a /etc/wsl.conf > /dev/null <<'EOF'
[boot]
systemd=true
EOF
```

Restart WSL from **PowerShell**: `wsl --shutdown`, then reopen Kali and verify:

```bash
sudo systemctl enable --now docker
docker version && docker compose version
```

> **Alternative:** Install **Docker Desktop for Windows** with the WSL2 backend and enable Kali integration under *Settings → Resources → WSL Integration*. Steps 4+ are identical either way.

### Step 4 — Get the project

```bash
# From inside Kali — clone or copy the project
git clone <your-repo-url> AutoSecForge-V.2
cd AutoSecForge-V.2

# Or, if the project lives on the Windows side:
cd /mnt/c/Users/<you>/Downloads/AutoSecForge-V.2
```

### Step 5 — Configure environment

```bash
cp .env.example .env
cp public/.env.example public/.env
nano public/.env     # change DB_PASSWORD, DB_ROOT_PASSWORD, ZAP_API_KEY
```

> The `.env` files are git-ignored — secrets never enter version control.

### Step 5b — Map the domain (required — access is domain-only)

The app only answers to `https://autosecforge.com`. Point that name at your machine:

```bash
# Linux / Kali / macOS:
echo "127.0.0.1 autosecforge.com" | sudo tee -a /etc/hosts
```

```powershell
# Windows (PowerShell as Administrator):
Add-Content -Path "$env:WINDIR\System32\drivers\etc\hosts" -Value "`n127.0.0.1 autosecforge.com"
```

> Accessing by IP (`https://127.0.0.1`) is intentionally blocked with `403 Forbidden`.

### Step 6 — Build and launch the stack

```bash
docker compose up -d --build
docker compose ps          # all services should be Up; db must be healthy
```

First build takes 5–15 minutes (image pulls + builds). MySQL auto-loads [database/schema.sql](database/schema.sql) on first boot, including the default admin account.

### Step 7 — Pull a local AI model

```bash
docker exec autosecforge-ollama ollama pull llama3
```

### Step 8 — Open the dashboard

Browse to **https://autosecforge.com** and log in. The image ships a **self-signed
certificate**, so your browser will warn once — accept it (or install a real cert,
see [Security Hardening](#security-hardening-notes)).

| | |
|---|---|
| URL | `https://autosecforge.com` |
| Email | `admin@autosecforge.local` |
| Password | `Admin@123` |

**Change this password immediately** (avatar menu → Change Password).

---

## Ollama Local AI Setup

All AI inference runs locally — nothing is sent to any cloud provider.

**Choosing a model** (set `OLLAMA_MODEL` in `public/.env`, then `docker compose restart ai-agent`):

| Model | Pull command | RAM needed | Notes |
|---|---|---|---|
| `llama3` (default) | `ollama pull llama3` | ~8 GB | Best balance of quality/speed |
| `llama3.1:8b` | `ollama pull llama3.1:8b` | ~8 GB | Newer, longer context |
| `mistral` | `ollama pull mistral` | ~6 GB | Faster, lighter |
| `phi3:mini` | `ollama pull phi3:mini` | ~4 GB | Low-RAM machines |
| `qwen2.5:14b` | `ollama pull qwen2.5:14b` | ~12 GB | Higher quality triage |

Run pulls inside the container: `docker exec autosecforge-ollama ollama pull <model>`.

**GPU acceleration (NVIDIA):** install `nvidia-container-toolkit` in Kali, then uncomment the `deploy.resources` GPU stanza under the `ollama` service in [docker-compose.yml](docker-compose.yml) and `docker compose up -d ollama`.

**Verify the AI pipeline** (services are internal-only, so check from inside the network):

```bash
docker exec autosecforge-ollama ollama list                              # installed models
docker exec autosecforge-mcp-router wget -qO- http://ai-agent:6400/health # {"status":"ok",...}
docker exec autosecforge-mcp-router wget -qO- http://localhost:6300/health# {"status":"ok",...}
```

The AI agent also exposes an **OpenAI-compatible endpoint** at `http://ai-agent:6400/v1/chat/completions` *on the internal network*, so any containerized OpenAI-SDK tool can point at it with a dummy API key. It is deliberately not published to the host.

---

## First Login & RBAC

Six roles control navigation, data visibility, and actions:

| Role | Dashboard | Trigger Scans | View All Jobs | Reports | Clients/Users Mgmt | Audit Log |
|---|---|---|---|---|---|---|
| **admin** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **manager** | ✅ | ✅ | ✅ | ✅ | clients only | ✅ |
| **analyst** | ✅ | ✅ | own jobs only | own reports | ❌ | ❌ |
| **auditor** | ✅ | ❌ | ✅ (read-only) | ✅ (read-only) | ❌ | ✅ |
| **client** | scoped | ❌ | own projects | own projects | ❌ | ❌ |
| **executive** | summary KPIs | ❌ | ✅ (read-only) | ✅ | ❌ | ❌ |

- Roles are stored on the `users` table; admins manage accounts from **Management → Users**.
- Sidebar sections (Scanning / Reporting / Management / System) render only for permitted roles.
- Every privileged action is written to `audit_log` with user, action, and timestamp.

---

## Running a Security Review

1. Go to **Scanning → Security Review** (`scan_trigger.php`).
2. Enter a target — public hostname, IP, or URL. Private/internal ranges (`10.x`, `172.16–31.x`, `192.168.x`, `127.x`, link-local) are **rejected by design**.
3. Select modules:
   - **Network** — `nmap -sV -T4 --open` service discovery
   - **DAST** — `nikto` web server scan
   - **SQLi** — `sqlmap --batch` injection probe
4. Click **Launch Security Review**. Progress steps display while tools run and the AI triages.
5. Results render in-page: **AI analysis** (executive summary, findings by severity, remediation) plus a collapsible **raw tool output** panel with copy/export buttons.
6. The job is saved to **Scanning → Scan Jobs** with full detail modal and re-scan shortcut.

---

## Reporting & Export

- **Reporting → Reports** (`report.php`) lists every completed review as a card with target, modules, date, and status.
- **Preview** opens the full AI analysis and raw evidence in a modal.
- **Export** downloads a formatted `.txt` report (`?export=<job-id>`) containing header metadata, AI triage, and raw scan output — suitable for attaching to tickets or client deliverables.
- **Audit Log** (admins/auditors) provides a tamper-evident trail of logins, scans, and exports.
- Client-facing templates live in [Documents/](Documents/) (runbook, architecture guide).

---

## Configuration Reference

`public/.env` (mounted into the app container):

| Variable | Default | Description |
|---|---|---|
| `DB_HOST` / `DB_PORT` | `db` / `3306` | MySQL container address |
| `DB_NAME` | `security_dashboard` | Schema name |
| `DB_USER` / `DB_PASSWORD` | `dashboard` / *change me* | App DB credentials |
| `DB_ROOT_PASSWORD` | *change me* | MySQL root (compose healthcheck) |
| `OLLAMA_MODEL` | `llama3` | Model the AI agent uses |
| `MCP_URL` | `http://mcp-router:6300` | Orchestrator endpoint |
| `AI_AGENT_URL` | `http://ai-agent:6400` | AI triage endpoint |
| `OASM_URL` | `http://oasm:6200` | Attack-surface service |
| `SQLMAP_URL` | `http://sqlmap:6000` | SQLMap API |
| `ZAP_API_KEY` | *change me* | ZAP daemon API key |

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `autosecforge.com` won't resolve | Add the hosts entry from [Step 5b](#step-5b--map-the-domain-required--access-is-domain-only). On WSL2 the browser usually runs on Windows — edit the **Windows** hosts file, not Kali's. |
| Browser shows `403 Forbidden` | You're hitting it by IP or wrong hostname — access is domain-locked. Use `https://autosecforge.com` exactly. |
| Certificate warning | Expected — the bundled cert is self-signed. Accept it once, or install a CA-signed cert (see Hardening). |
| Dashboard unreachable at the domain | `docker compose ps` — if `app` is restarting, check `docker compose logs app`. Confirm the hosts entry maps to `127.0.0.1`. |
| Login fails with DB error | DB may still be initializing — wait for `db` to report *healthy*. To rebuild the schema: `docker compose down -v && docker compose up -d` (**destroys data**). |
| AI analysis says "triage unavailable" | Model not pulled: `docker exec autosecforge-ollama ollama pull llama3`. Confirm with `curl localhost:11434/api/tags`. |
| AI responses extremely slow | CPU inference is slow for big models — switch to `phi3:mini`/`mistral`, or enable the GPU stanza. |
| Scans return "Invalid or private target" | Working as intended — SSRF guard blocks internal ranges. Test against a host you're authorized to scan (e.g., `scanme.nmap.org`). |
| MCP router can't reach tool containers | It needs the Docker socket: confirm `/var/run/docker.sock` is mounted and tool containers (`autosecforge-nmap`, `-nikto`, `-sqlmap`) are **Up** (`docker compose ps`). |
| SonarQube exits with `vm.max_map_count` error | `sudo sysctl -w vm.max_map_count=262144` (persist in `/etc/sysctl.conf`). |
| Port 80/443 already in use on host | Another web server owns it — stop it, or change the loopback host port in [docker-compose.yml](docker-compose.yml) (e.g., `"127.0.0.1:8443:443"`) and browse `https://autosecforge.com:8443`. |
| WSL clock drift breaks TLS/apt | `sudo hwclock -s` or restart WSL (`wsl --shutdown`). |

Logs for any service: `docker compose logs -f <service>` (e.g., `app`, `mcp-router`, `ai-agent`, `ollama`).

---

## Security Hardening Notes

Before any non-lab deployment:

1. **Rotate every default credential** — admin password, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `ZAP_API_KEY`.
2. **Access is already domain-locked and loopback-bound.** App ports bind to `127.0.0.1` only and Apache 403s any non-`autosecforge.com` Host — keep it that way. Only the app is host-reachable; every other service is internal to `asf-net`.
3. **Replace the self-signed certificate.** The image generates a self-signed cert at `/etc/ssl/asf/`. For real use, mount a CA-signed cert/key over those paths (e.g. add a volume `./ssl/autosecforge.crt:/etc/ssl/asf/autosecforge.crt:ro`) or terminate TLS at a reverse proxy.
4. The MCP router mounts the **Docker socket (read-only)** to exec into tool containers — treat that container as privileged; it has no host port and must stay that way.
5. `.htaccess` already denies `.env`/`.git`, sets security headers, and default-denies PHP outside the allow-list — keep it intact if you change the web root.
6. SSRF guards exist at both PHP and Node layers; don't relax the private-IP filters.
7. Review `audit_log` regularly; the auditor role exists for exactly this.

---

## Project Structure

```
AutoSecForge-V.2/
├── docker-compose.yml        # Full 12-service stack
├── Dockerfile                # PHP 8.3 + Apache app image
├── database/schema.sql       # Users (RBAC), projects, scan_jobs, findings, audit_log
├── public/                   # Web root
│   ├── home.php              #   Dashboard (KPIs, charts, tool health)
│   ├── scan_trigger.php      #   Security review launcher
│   ├── scan_jobs.php         #   Job history + detail modal
│   ├── report.php            #   Reports grid + .txt export
│   ├── api/                  #   JSON endpoints (stats, recent_scans, trigger_scan, ai_analyze)
│   └── .htaccess             #   Security headers + PHP allow-list
├── views/partials/           # AdminLTE 3.2 header/footer layout
├── src/                      # Database.php (PDO), auth.php (sessions/RBAC), helpers.php
├── ai-agent/                 # Flask service → Ollama (triage + OpenAI-compatible API)
├── mcp-server/               # Express orchestrator (→ mcp-router service)
├── tool-wrappers/            # Dockerfiles/APIs for nmap, nikto, sqlmap, oasm, pentest
└── Documents/                # Architecture diagrams, client runbook, SAST report
```

---

*AutoSecForge Pro v12.1 — built for authorized security testing. © 2026 Tamal Kanti Mazumder.*
