<div align="center">
🛡️ AutoSecForge
<p align="center">
  <img src="https://img.shields.io/badge/Version-2.1.0-blueviolet?style=for-the-badge&logo=gitbook&logoColor=white" alt="Version">
  <img src="https://img.shields.io/badge/License-Enterprise-green?style=for-the-badge&logo=data-security&logoColor=white" alt="License">
  <img src="https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Python-3.12+-FFD43B?style=for-the-badge&logo=python&logoColor=white" alt="Python">
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/Kubernetes-Native-326CE5?style=for-the-badge&logo=kubernetes&logoColor=white" alt="Kubernetes">
  <img src="https://img.shields.io/badge/AI-LLM%20Powered-FF6F00?style=for-the-badge&logo=openai&logoColor=white" alt="AI LLM">
</p>
<p align="center">
  <strong>Next-Generation AI-Driven Application Security, Product Security, and DevSecOps Orchestration Platform</strong>
</p>
<p align="center">
  <a href="#-platform-overview">Overview</a> •
  <a href="#-system-architecture">Architecture</a> •
  <a href="#-core-capabilities">Capabilities</a> •
  <a href="#-mcp-connector-architecture">MCP Layer</a> •
  <a href="#-deployment--initialization">Quick Start</a> •
  <a href="#-update-v210">What's New</a>
</p>
</div>
---
⚡ Platform Overview
AutoSecForge consolidates vulnerability intelligence, security posture management, attack-surface visibility, and enterprise compliance reporting into a unified, single-pane-of-glass workspace.
Designed for modern security engineering teams, MSSPs, and vCISOs, the platform aggregates real-time metrics across SAST, DAST, SCA, Mobile Security, Container Security, and Open Attack Surface Management (OASM). By shifting context through an advanced Model Context Protocol (MCP) execution layer, AutoSecForge natively automates true threat triaging, code-level remediation modeling, and pipeline validation guards.
> 🎯 **Mission:** Reduce mean-time-to-remediate (MTTR) by 80% through AI-native security orchestration.
---
🏗️ System Architecture
High-Level Architecture Diagram
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        AutoSecForge Enterprise UI                             │
│         [ PHP 8.3 / Tailwind CSS / Alpine.js / Chart.js Workspaces ]        │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                         REST APIs / WebSockets / gRPC
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Platform Core Backend                                 │
│    [ PHP API Gateway ]  ◄──►  [ Redis Cluster ]  ◄──►  [ PostgreSQL MT ]    │
│                              [ NATS Event Bus ]                               │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                    Internal Microservice Router Mesh
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    Python Microservices (FastAPI Engine)                      │
│   • Pentest Automation APIs   • Vulnerability Normalization  • AI Builder    │
│   • SBOM Generator            • Compliance Mapper            • Report Engine │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
        ┌───────────────────────────┴───────────────────────────┐
        │                                                       │
        ▼                                                       ▼
┌─────────────────────────────┐                     ┌─────────────────────────┐
│   MCP Secure Connectors     │                     │  Security Engine Pods   │
│     [ JSON-RPC Mesh ]       │                     │  (Docker / K8s / WASM)  │
└─────────────────────────────┘                     └─────────────────────────┘
        │                                                       │
   ┌────┼────┬────────┬────────┐                               │
   ▼    ▼    ▼        ▼        ▼                               ▼
┌────┐┌────┐┌────┐┌──────┐┌────────┐                  ┌─────────────────┐
│SQ  ││ZAP ││Dep ││MobSF ││K8s Sec │                  │  Target Assets  │
│MCP ││MCP ││MCP ││ MCP  ││  MCP   │                  │ (Cloud & APIs)  │
└────┘└────┘└────┘└──────┘└────────┘                  └─────────────────┘
   ▲    ▲    ▲        ▲        ▲                               ▲
   └────┼────┴────────┴────────┘                               │
        │                                          Trigger Execution
        ▼                                          Action & Remediation
┌─────────────────────────────────────────────────────────────────────────────┐
│                          🤖 AI Security Core                                │
│   • Threat Triage Engine    • LLM Summarization    • Attack-Path Graph      │
│   • False-Positive Filter   • Auto-Remediation     • Risk Scoring Model     │
│   • Agentic Red Teaming     • SBOM Drift Detection • Compliance Evidence    │
└─────────────────────────────────────────────────────────────────────────────┘
```
Data Flow
```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Scanners   │────▶│   Normalizer │────▶│  AI Triage   │────▶│  Remediation │
│  (SAST/DAST) │     │   (Unified)  │     │   (LLM/ML)   │     │  (Auto-Fix)  │
└──────────────┘     └──────────────┘     └──────────────┘     └──────────────┘
       │                    │                    │                    │
       └────────────────────┴────────────────────┘                    │
                          │                                           │
                          ▼                                           ▼
                   ┌──────────────┐                          ┌──────────────┐
                   │  PostgreSQL  │                          │   PR Bot     │
                   │   (Tenant)   │                          │  (GitHub)    │
                   └──────────────┘                          └──────────────┘
```
---
🛡️ Core Capabilities
AppSec & ProdSec Alignment
Capability	Description	Status
Multi-Scanner Aggregation	Normalizes disparate raw logs from SAST, DAST, API testing into unified, prioritized risk records	✅ Live
OWASP & Compliance Auditing	Maps infrastructure vulnerabilities dynamically to OWASP Top 10, NIST, ISO 27001	✅ Live
Lifecycle Visibility	Tracks real-time architectural components, historical pentest inventory, compliance evidence trails	✅ Live
SBOM Management	Continuous SPDX/CycloneDX generation with dependency drift detection	🆕 v2.1
Agentic Red Teaming	AI-driven prompt-injection and adversarial testing for LLM integrations	🆕 v2.1
Intelligent Pipeline Orchestration
Capability	Description	Status
AI False-Positive Mitigation	Evaluates findings using secure local ML models to weed out noise	✅ Live
Automated Patch Delivery	Delivers instantly actionable code fixes directly inside developer PRs	✅ Live
Kubernetes Control Validation	Continuous cluster analysis mapping misconfigurations and access exposures	✅ Live
Attack Path Analysis	Graph-based security trees identifying critical exposure vectors	🆕 v2.1
Runtime K8s Threat Module	Cloud runtime monitoring with inline infrastructure anomaly scanners	🆕 v2.1
---
🔌 MCP Connector Architecture
AutoSecForge separates execution tools from AI context generation using the standardized Model Context Protocol (MCP) framework. This ensures fluid JSON-RPC structural exchanges without custom orchestration overhead.
```
┌─────────────────────────┐
│     AI Security Core    │
│  (Threat Triage + LLM)  │
└─────────────────────────┘
            ▲
            │ Standardized JSON-RPC 2.0
            ▼
┌─────────────────────────┐
│   MCP Secure Connector  │◄───[ NATS Event Bus ]
│    (Auth + Rate Limit)  │
└─────────────────────────┘
            ▲
    ┌───────┼───────┬───────────┬───────────┐
    ▼       ▼       ▼           ▼           ▼
┌───────┐ ┌───────┐ ┌───────┐ ┌───────┐ ┌───────────┐
│SonarQ │ │ OWASP │ │ DepCh │ │ MobSF │ │ Kubernetes│
│ MCP   │ │ ZAP   │ │ MCP   │ │ MCP   │ │   MCP     │
│(Static)│ │(DAST) │ │ (SCA) │ │(Mobile)│ │(Runtime) │
└───────┘ └───────┘ └───────┘ └───────┘ └───────────┘
```
Out-of-the-Box Integrations
Connector	Type	Description	Version
`SonarQube MCP`	SAST	Source pipeline alignment, quality gates	2.1.0
`OWASP ZAP MCP`	DAST	Automated context-driven baseline scans	2.1.0
`Dependency-Check MCP`	SCA	Continuous composition audits	2.1.0
`MobSF MCP`	Mobile	Mobile binary application analysis	2.1.0
`Kubernetes Security MCP`	Runtime	Direct cluster threat visibility	2.1.0
🆕 Trivy MCP	Container	Container image & filesystem scanning	2.1.0
🆕 Semgrep MCP	SAST	Lightweight static analysis for polyglot repos	2.1.0
🆕 GitHub Advanced Security MCP	SCM	Secret scanning, dependency review, code scanning	2.1.0
---
🎨 Enterprise UI/UX Design System
The layout system borrows UX logic pioneered by modern security interfaces like CrowdStrike Falcon, Wiz, and Microsoft Defender XDR.
```
┌──────────────────────────────────────────────────────────────────────────────┐
│  [🛡️ Dashboard]  Attack Surface Score: 87/100     Active Threats: 3        │
│  [🔍 OASM] [📊 AppSec] [☸️ K8s] [📱 Mobile] [📋 Compliance] [⚙️ Settings]   │
├──────────────────────────────────────────────────────────────────────────────┤
│  ⚡ Real-Time Insights                                                      │
│  ├── [SCA] CVE-2024-XXXX (Critical) → Dependency Tree Ingestion            │
│  ├── [OASM] Port 8080 exposed on api.subdomain.domain.com                   │
│  └── [K8s]  Privileged container detected in namespace 'production'          │
├──────────────────────────────────────────────────────────────────────────────┤
│  📊 Visual Analytics                                                         │
│  ├── [Vulnerability Heatmap]    [MITRE ATT&CK Mapping]                       │
│  ├── [Attack Path Graph]        [SBOM Dependency Tree]                       │
│  └── [Compliance Scorecard]     [Risk Trend Timeline]                        │
├──────────────────────────────────────────────────────────────────────────────┤
│  🤖 AI Assistant                                                             │
│  └── "3 critical findings require immediate attention. Generate fix? [Y/n]"  │
└──────────────────────────────────────────────────────────────────────────────┘
```
Design Principles
Executive Command Viewports: Immediate access to API Risk parameters, Cloud Security Posture metrics, and exposure logs
Analyst Workspaces: Dark mode workspace with live activity grids, incident timelines, and interactive dependency mapping charts
AI Copilot Panel: Context-aware assistant for threat explanation, remediation guidance, and report generation
🆕 Custom Dashboard Builder: Drag-and-drop widgets for personalized security views
---
💻 Technical Infrastructure Stack
Backend & Pipeline	Database & Compute	Security Integrations	AI & ML
PHP 8.3 Gateway Architecture	PostgreSQL 15 (Tenant Registry)	SonarQube Engine	Local LLM (Ollama/Llama 3)
Python 3.12+ Microservices	Redis 7 Cluster (Job Queue)	OWASP ZAP Stack	OpenAI / Azure OpenAI
FastAPI Engine Framework	NATS (Event Streaming)	OWASP Dependency-Check	Mistral / Claude
NGINX Reverse Proxy	ClickHouse (Analytics)	Mobile Security Framework	Custom Risk Scoring Models
🆕 Go Sidecar (WASM)	🆕 MinIO (Object Store)	🆕 Trivy Scanner	🆕 Agentic Framework
---
👥 Multi-Tenant Client Management
Tailored specifically for Managed Security Service Providers (MSSPs) and enterprise business divisions requiring strict target isolation.
```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│  Client Alpha   │       │   Client Beta   │       │  Client Gamma   │
│ [Isolated Vault]│       │ [Isolated Vault]│       │ [Isolated Vault]│
│  • Custom Brand │       │  • Custom Brand │       │  • Custom Brand │
│  • Own Scanners │       │  • Own Scanners │       │  • Own Scanners │
└────────┬────────┘       └────────┬────────┘       └────────┬────────┘
         │                         │                         │
         └─────────────────────────┼─────────────────────────┘
                                 │
                                 ▼
         ┌─────────────────────────────────────┐
         │      Global Multi-Tenant RBAC       │
         │  [Admin] [SecManager] [Analyst]     │
         │  [ClientViewer] [API Service]       │
         └─────────────────────────────────────┘
```
Tenant Boundary Vaults: Isolated data management with support for custom organization branding assets and client logo profiles
Granular RBAC Matrix: Built-in validation access groups spanning `Administrator`, `Security Manager`, `Security Analyst`, `API Service`, and read-only `Client Viewer` permissions
🆕 SSO / SAML 2.0 / OIDC: Enterprise identity provider integration
---
📊 Enterprise Reporting & Deliverables
Generate production-grade audit-ready documentation profiles at the click of a button.
Format	Use Case	Status
`PDF`	Executive summaries, compliance reports	✅
`DOCX`	Detailed pentest reports, remediation plans	✅
`Excel`	Asset inventories, vulnerability matrices	✅
`CSV`	Raw data export, SIEM ingestion	✅
`JSON`	API consumption, automation pipelines	✅
🆕 `SARIF`	Standardized static analysis results	🆕 v2.1
🆕 `CycloneDX`	SBOM exports for supply chain	🆕 v2.1
🆕 `STIX/TAXII`	Threat intelligence sharing	🆕 v2.1
Export Bundles
Deliver complete compliance bundles with evidence attachments, penetration testing validation checklists, and live asset inventory sheets.
---
🚀 Deployment & Initialization
Prerequisites
Docker Engine 24.0+
Docker Compose v2+
kubectl (for K8s deployments)
8 GB RAM minimum / 16 GB recommended
Local Platform Bootstrapping
Spin up the core services and default testing instances in a unified docker workspace:
```bash
# Clone the repository
git clone https://github.com/autosecforge/autosecforge.git
cd autosecforge

# Copy environment configuration
cp .env.example .env

# Spin up the complete security mesh
docker compose up --build -d

# Verify services
docker compose ps
```
Kubernetes Deployment
```bash
# Apply Helm chart
helm repo add autosecforge https://charts.autosecforge.io
helm install autosecforge autosecforge/autosecforge   --namespace autosecforge   --create-namespace   --values values-production.yaml
```
Core URLs & Port Assignments
Service	URL	Description
🖥️ Platform Dashboard	http://localhost:8080	Main security portal
🧬 SonarQube Engine	http://localhost:9000	Code quality & SAST
🔍 OWASP ZAP	http://localhost:8081	DAST scanner
📊 Grafana	http://localhost:3000	Metrics & monitoring
🗄️ MinIO Console	http://localhost:9001	Object storage
Seed Credentials
Parameter	Value
Default System Identity	`admin@autosecforge.local`
Initial Access Value	`ChangeMe123!`
> ⚠️ **Security Notice:** Change the default admin password within the platform account management portal immediately following successful initialization.
---
🆕 What's New in v2.1.0
🎯 Update Highlights
Feature	Category	Impact
Agentic Red Teaming Engine	AI Security	Autonomous adversarial testing for LLM integrations
Continuous SBOM Management	Supply Chain	SPDX/CycloneDX generation with drift alerts
Attack Path Analysis	Risk Management	Graph-based critical exposure vector identification
Runtime K8s Threat Module	Cloud Security	Inline anomaly detection for container runtimes
Trivy MCP Connector	Container Scanning	Native container image & filesystem vulnerability scanning
Semgrep MCP Connector	SAST	Polyglot lightweight static analysis
GitHub Advanced Security MCP	SCM	Native secret scanning & dependency review integration
SARIF / STIX Export	Reporting	Industry-standard format support
Custom Dashboard Builder	UX	Personalized security views with drag-and-drop
SSO / SAML 2.0 / OIDC	Identity	Enterprise identity provider integration
ClickHouse Analytics	Data	High-performance security analytics warehouse
MinIO Object Storage	Storage	S3-compatible artifact & evidence storage
Migration Guide
```bash
# Backup existing data
docker compose exec postgres pg_dump -U autosecforge autosecforge > backup.sql

# Pull latest images
docker compose pull

# Apply database migrations
docker compose run --rm backend php artisan migrate

# Restart services
docker compose up -d
```
---
🗺️ Product Roadmap
Q3 2026 — v2.2.0 (Planned)
[ ] AI-Powered Threat Hunting — Autonomous anomaly detection across cloud workloads
[ ] Zero Trust Network Access (ZTNA) — Micro-segmentation policy validation
[ ] API Security Posture Management (ASPM) — Full lifecycle API security
[ ] Federated Learning for Security Models — Privacy-preserving model improvement across tenants
Q4 2026 — v3.0.0 (Vision)
[ ] Autonomous Security Operations Center (SOC) — Self-healing security with minimal human intervention
[ ] Quantum-Safe Cryptography Audit — Post-quantum readiness assessment
[ ] Extended Detection and Response (XDR) — Cross-layer threat correlation
[ ] Digital Twin Security Simulation — Predictive security modeling for infrastructure changes
---
🤝 Contributing
We welcome contributions from the security community! Please see our Contributing Guide for details.
```bash
# Fork and clone
git clone https://github.com/YOUR_USERNAME/autosecforge.git
cd autosecforge

# Create feature branch
git checkout -b feature/amazing-feature

# Commit and push
git commit -m "Add amazing feature"
git push origin feature/amazing-feature

# Open Pull Request
```
---
📄 License
AutoSecForge is licensed under the Enterprise License. See LICENSE for details.
---
<div align="center">
⬆ Back to Top
Built with ❤️ by the AutoSecForge Security Engineering Team
</div>