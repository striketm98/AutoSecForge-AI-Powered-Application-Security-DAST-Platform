#!/usr/bin/env bash
# AutoSecForge Pro — One-command local startup (no Docker required)
# Usage: bash start.sh
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$REPO/.logs"
mkdir -p "$LOG_DIR"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
ok()   { echo -e "${GREEN}✓${NC} $*"; }
fail() { echo -e "${RED}✗${NC} $*"; }
info() { echo -e "${CYAN}→${NC} $*"; }
warn() { echo -e "${YELLOW}!${NC} $*"; }

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║      AutoSecForge Pro  — Local Startup   ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════╝${NC}"
echo ""

# ── Config ──────────────────────────────────────────────────────────────────
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-security_dashboard}"
DB_USER="${DB_USER:-dashboard}"
DB_PASSWORD="${DB_PASSWORD:-dashboard123}"
DB_ROOT="${DB_ROOT:-root}"
APP_NAME="${APP_NAME:-AutoSecForge Pro}"
PHP_PORT="${PHP_PORT:-8080}"

export DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD APP_NAME

# ── Check dependencies ───────────────────────────────────────────────────────
info "Checking dependencies..."
for cmd in php python3 mysql; do
    if command -v $cmd &>/dev/null; then
        ok "$cmd found"
    else
        fail "$cmd NOT FOUND — install it first"
        [[ $cmd == "php" ]] && echo "  Ubuntu/WSL: sudo apt install php8.3-cli php8.3-mysql php8.3-mbstring"
        [[ $cmd == "python3" ]] && echo "  Ubuntu/WSL: sudo apt install python3 python3-pip"
        [[ $cmd == "mysql" ]] && echo "  Ubuntu/WSL: sudo apt install mysql-server"
        exit 1
    fi
done
python3 -c "import flask" 2>/dev/null || { warn "Flask not found, installing..."; pip3 install flask --break-system-packages -q; }
ok "Flask available"

# ── MySQL ────────────────────────────────────────────────────────────────────
info "Starting MySQL..."
if command -v service &>/dev/null; then
    sudo service mysql start 2>/dev/null || true
elif command -v systemctl &>/dev/null; then
    sudo systemctl start mysql 2>/dev/null || true
fi
sleep 2

if mysql -u"$DB_ROOT" -e "SELECT 1" &>/dev/null 2>&1; then
    ok "MySQL running"
    mysql -u"$DB_ROOT" 2>/dev/null << SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    ok "Database '$DB_NAME' ready"
    mysql -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$REPO/database/schema.sql" 2>/dev/null
    ok "Schema applied"
else
    warn "Could not connect to MySQL as root — assuming DB already configured"
fi

# ── Kill any existing services ────────────────────────────────────────────────
info "Clearing ports..."
for port in $PHP_PORT 6100 6200 6300 6400; do
    fuser -k ${port}/tcp 2>/dev/null || true
done
sleep 1

# ── Start Python microservices ────────────────────────────────────────────────
info "Starting Python microservices..."
python3 "$REPO/tools/mcp-hackstrike/mcp_hackstrike_api.py"      > "$LOG_DIR/mcp-hackstrike.log"  2>&1 & echo $! > /tmp/asf_pid_6300
python3 "$REPO/tools/openai-free-agents/openai_free_agents_api.py" > "$LOG_DIR/openai-agents.log" 2>&1 & echo $! > /tmp/asf_pid_6400
python3 "$REPO/tools/oasm/oasm_api.py"                          > "$LOG_DIR/oasm.log"            2>&1 & echo $! > /tmp/asf_pid_6200
python3 "$REPO/tools/pentest-python/pentest_api.py"             > "$LOG_DIR/pentest.log"         2>&1 & echo $! > /tmp/asf_pid_6100
sleep 3

# ── Start PHP server ──────────────────────────────────────────────────────────
info "Starting PHP web server on port $PHP_PORT..."
php -S 0.0.0.0:$PHP_PORT -t "$REPO/public/" \
    -d session.save_path=/tmp \
    -d display_errors=0 \
    -d error_reporting=0 \
    > "$LOG_DIR/php.log" 2>&1 &
echo $! > /tmp/asf_pid_8080
sleep 2

# ── Status check ─────────────────────────────────────────────────────────────
echo ""
echo -e "${CYAN}═══════════════════ STATUS ═══════════════════${NC}"
ALL_OK=true
declare -A svc_names=([6100]="Pentest API " [6200]="OASM API    " [6300]="MCP HackStrk" [6400]="AI Agents   " [$PHP_PORT]="PHP Dashboard")
for port in 6100 6200 6300 6400 $PHP_PORT; do
    python3 -c "
import socket
s=socket.socket(); s.settimeout(1)
if s.connect_ex(('127.0.0.1',$port))==0: print('UP')
else: print('DOWN')
s.close()" 2>/dev/null | read -r stat || stat="DOWN"
    if [ "$stat" = "UP" ]; then ok "${svc_names[$port]:-Port $port}  :$port"; else fail "${svc_names[$port]:-Port $port}  :$port"; ALL_OK=false; fi
done

echo ""
if $ALL_OK; then
    echo -e "${GREEN}╔══════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✓  AutoSecForge Pro is RUNNING          ║${NC}"
    echo -e "${GREEN}║                                          ║${NC}"
    echo -e "${GREEN}║  URL:  http://localhost:$PHP_PORT           ║${NC}"
    echo -e "${GREEN}║  User: admin@cyber-security.local        ║${NC}"
    echo -e "${GREEN}║  Pass: ChangeMe123!                      ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════╝${NC}"
else
    warn "Some services failed to start. Check logs in .logs/"
fi
echo ""
echo "Logs: $LOG_DIR/"
echo "Stop: bash stop.sh"
