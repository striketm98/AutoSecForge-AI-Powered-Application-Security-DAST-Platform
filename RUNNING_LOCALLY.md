# Running AutoSecForge Pro Locally (WSL / Linux / Mac)

## Prerequisites
```bash
# Ubuntu / WSL
sudo apt install php8.3-cli php8.3-mysql php8.3-mbstring mysql-server python3 python3-pip
pip3 install flask --break-system-packages
```

## Start
```bash
bash start.sh
```

## Access
- URL: http://localhost:8080
- Admin: `admin@cyber-security.local` / `ChangeMe123!`
- Manager: `manager@cyber-security.local` / `ChangeMe123!`
- Analyst: `analyst@cyber-security.local` / `ChangeMe123!`
- Client: `client@cyber-security.local` / `ChangeMe123!`

## Stop
```bash
bash stop.sh
```

## Docker (alternative)
```bash
docker compose up --build
# Access at http://localhost:8080
```

## Services
| Service           | Port | Purpose                        |
|-------------------|------|--------------------------------|
| PHP Dashboard     | 8080 | Main web UI                    |
| MySQL             | 3306 | Database                       |
| Pentest API       | 6100 | Python pentest validation tool |
| OASM API          | 6200 | Attack surface management      |
| MCP HackStrike    | 6300 | JSON-RPC connector fabric      |
| OpenAI Free Agents| 6400 | AI triage/remediation agents   |

## Schema Fix Applied
MySQL 8.0 compatibility patch applied — `ADD COLUMN IF NOT EXISTS`
converted to use `information_schema` checks. Works on MySQL 8.0+.
