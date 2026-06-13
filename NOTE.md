# AutoSecForge-V.2 — Troubleshooting Notes

Operational runbook of every problem hit while standing up AutoSecForge on the
remote Kali pentest box, with the root cause and the exact fix for each.

> **Environment reality (read this first).**
> The running stack lives on a **separate remote PC** (`Remote-Pentest-Desktop`)
> at **`/opt/AutoSecForge-V.2`**, user **root**, **~3.8 GB RAM**, Docker image
> prefix `kali-`. The Windows dev folder
> `C:\Users\tamal\Downloads\AutoSecForge-V.2` is **unreachable** from the remote,
> and `/mnt/c` on the remote is **not** the Windows machine. Windows `.wslconfig`
> has **no effect** on the remote.
> **Consequence:** code fixes only take effect on the remote after a
> `git pull` / re-deploy there. Editing the Windows copy alone changes nothing.

---

## 0. Access / security model (intended state)

| Rule | Implementation |
| --- | --- |
| Only the PHP app is reachable from host | App binds `127.0.0.1:80` + `127.0.0.1:443`; every other service uses `expose:` (internal network only) |
| Not reachable by IP / LAN | Loopback-only port binding + vhost denies unknown `Host` |
| Domain-only access | vhost `RewriteCond %{HTTP_HOST} !^autosecforge\.com$ → [F]`; `*:443` catch-all denies unknown hosts |
| HTTPS required | `*:80` for the domain 301→HTTPS; self-signed cert at `/etc/ssl/asf/` |
| Hosts mapping | `127.0.0.1 autosecforge.com` in `/etc/hosts` (host machine) |

Default login: `admin@autosecforge.local` / `Admin@123` — **change after first login.**

Secrets (`.env`, `public/.env`) are **gitignored** — never committed, never on
GitHub. They live only on the box.

---

## 1. Ollama 500 — `POST /api/chat` Internal Server Error  ⭐ most recent

**Symptom**
```
Ollama error: 500 Server Error: Internal Server Error for url: http://ollama:11434/api/chat
```

**Diagnosis (from `docker logs autosecforge-ollama`)**
```
print_info: model params  = 8.03 B
load_tensors: CPU_REPACK model buffer size = 3744.00 MiB
error="llama-server process has terminated: signal: killed"
500 | POST "/api/chat"
```
`llama3` is an **8B** model needing ~5–6 GB. The box has **3.8 GB** → the kernel
**OOM-kills** llama-server mid-load → 500. (`signal: killed` = OOM.)

**Root cause:** AI agent was requesting **`llama3`** (the compose default), which
does not fit in RAM.

**Fix — use a model that fits**
```bash
cd /opt/AutoSecForge-V.2

# pull a small model (already present in our case: qwen2.5:1.5b ≈ 986 MB)
docker exec autosecforge-ollama ollama pull qwen2.5:1.5b

# point the agent at it
grep -q '^OLLAMA_MODEL=' .env \
  && sed -i 's/^OLLAMA_MODEL=.*/OLLAMA_MODEL=qwen2.5:1.5b/' .env \
  || echo 'OLLAMA_MODEL=qwen2.5:1.5b' >> .env
docker compose up -d ai-agent
docker exec autosecforge-ai-agent printenv OLLAMA_MODEL    # → qwen2.5:1.5b
```

**Verify**
```bash
# model loads in RAM, no OOM:
docker exec autosecforge-ollama ollama run qwen2.5:1.5b "say hi"   # → "Hello! ..."

# HTTP path (ai-agent/ollama images have no curl — use a throwaway curl container):
docker run --rm --network autosecforge-v2_asf-net curlimages/curl:latest \
  -s http://ollama:11434/api/chat \
  -d '{"model":"qwen2.5:1.5b","messages":[{"role":"user","content":"hi"}],"stream":false}'

# logs should now show Qwen2.5-1.5B load + "200 | POST /api/chat"
docker logs --tail 15 autosecforge-ollama
```

**Gotchas**
- `curl` is **not** installed in the `ai-agent` or `ollama` images
  (`exec: "curl": executable file not found`). Use `ollama run` for a RAM test,
  or a `curlimages/curl` container on the `asf-net` network for an HTTP test.
- If `printenv` shows `qwen2.5:1.5b` but logs **still** load `llama3`, the model
  name is **hardcoded** in `ai-agent/` source — grep and fix it there.
- Too tight even for 1.5b? Drop to `qwen2.5:0.5b` (~400 MB).
- Reclaim space / prevent reuse: `docker exec autosecforge-ollama ollama rm llama3:latest`.

**Status:** ✅ `qwen2.5:1.5b` loads and replies in RAM (OOM gone). Final confirm =
trigger the app's AI feature; it should return a result instead of 500.

---

## 2. Build error — `sqlmap/sqlmap:latest` pull access denied

**Symptom:** `failed to solve: sqlmap/sqlmap:latest: pull access denied`
**Cause:** no such image on Docker Hub. Also **not** in Alpine apk
(`apk add sqlmap` → `sqlmap (no such package)`).
**Fix** — `tool-wrappers/sqlmap/Dockerfile`:
```dockerfile
FROM python:3.12-alpine
RUN pip install --no-cache-dir sqlmap
CMD ["sleep", "infinity"]
```

---

## 3. Build error — nikto not in Alpine apk

**Cause:** `apk add nikto` fails; nikto isn't packaged for Alpine.
**Fix** — `tool-wrappers/nikto/Dockerfile` (clone + Perl shim):
```dockerfile
FROM alpine:3.19
RUN apk add --no-cache perl perl-net-ssleay git \
 && git clone --depth 1 https://github.com/sullo/nikto /opt/nikto \
 && apk del git \
 && printf '#!/bin/sh\nexec perl /opt/nikto/program/nikto.pl "$@"\n' > /usr/local/bin/nikto \
 && chmod +x /usr/local/bin/nikto
CMD ["sleep", "infinity"]
```
(nmap is fine: `FROM alpine:3.19` + `apk add --no-cache nmap nmap-scripts`.)

---

## 4. Build error — PHP curl ext: `libcurl >= 7.29.0 not found`

**Cause:** the `curl` apt package has no dev headers for `docker-php-ext-install curl`.
**Fix** — in the app `Dockerfile`, install dev headers:
```dockerfile
RUN apt-get update && apt-get install -y curl libcurl4-openssl-dev openssl ...
```

---

## 5. Build error — `COPY .env ... "/.env": not found`

**Cause:** `.env` is gitignored → absent from the Docker build context.
**Fix:** **remove** the `COPY .env` line from the Dockerfile. The compose volume
mount of `./public` supplies `.env` at runtime. Secrets must never be baked into
the image.

---

## 6. 403 Forbidden on `https://autosecforge.com`

**Cause:** `public/.htaccess` mixed Apache **2.2** `Order/Deny/Allow` with the
vhost's **2.4** `Require` → blanket deny.
**Fix:**
- Rewrite `.htaccess` to **pure 2.4 `Require`** (never mix the two systems).
- Change vhost `_default_:443` → `*:443` catch-all.

Canonical `.htaccess` access block:
```apache
Options -Indexes
<FilesMatch "(^\.env|\.env\.|\.htpasswd|\.git)">
    Require all denied
</FilesMatch>
<FilesMatch "\.(php|phtml)$">
    Require all denied
</FilesMatch>
<FilesMatch "^(index|login|home|dashboard|logout|change_password|scan_trigger|scan_jobs|report|clients|checklist|audit|addons|settings|review|deliverables|oasm|export|import)\.php$">
    Require all granted
</FilesMatch>
<FilesMatch "^(stats|recent_scans|trigger_scan|ai_analyze|findings)\.php$">
    Require all granted
</FilesMatch>
```

---

## 7. DB — `Access denied for user 'dashboard'@'...'`

**Cause:** `DB_PASSWORD` differed between root `.env` (MySQL/compose) and
`public/.env` (PHP) — or was changed **after** the MySQL volume already
initialized (init only seeds the user on a fresh volume).
**Fix:**
1. Make `DB_USER` / `DB_PASSWORD` / `DB_NAME` / `DB_ROOT_PASSWORD` **identical**
   in both `.env` files. Replace any `CHANGE_ME_` placeholders with real values.
2. Re-initialize the MySQL volume:
```bash
docker compose down
docker volume rm autosecforge-v2_mysql-data
docker compose up -d
```

---

## 8. Login — "Invalid email or password" with correct creds

**Cause:** the seeded admin bcrypt hash was a digest of "password" with a
hand-edited `$2y$12$...` prefix → verifies nothing. **You cannot change a bcrypt
prefix/body by hand** — the hash must be recomputed.
**Fix:** real bcrypt of `Admin@123` in `database/schema.sql`:
```
$2b$12$6mQ4df5e3nLI6rPa7VPOde2wQzVpgWOsObVdVEcsRIVCDQMTX/ZKi
```
For an already-seeded DB, `UPDATE users SET password_hash='<above>' WHERE email='admin@autosecforge.local';`
(PHP `password_verify` accepts the `$2b$` prefix.)

---

## 9. Broken / unstyled UI — CSP blocked CDN assets

**Symptom:** Bootstrap/AdminLTE/jQuery/Chart.js/Font Awesome blocked in console.
**Cause:** CSP only allowed `'self'` (+cdnjs), blocking `cdn.jsdelivr.net` and
Google Fonts. The running container also kept serving an **old baked CSP**.
**Fix:** set CSP in `public/.htaccess` (volume-mounted → no rebuild) and have it
**override** the vhost via `Header always unset` then set:
```apache
Header always unset Content-Security-Policy
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data:; connect-src 'self'; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net;"
```
Allowed CDNs: jsdelivr (Bootstrap/AdminLTE/jQuery/Chart.js), cdnjs (Font Awesome),
Google Fonts (Inter).

---

## 10. Scan warning — `connect EACCES /var/run/docker.sock`

**Cause:** `mcp-router` orchestrator must read the Docker socket to `docker exec`
into scanner containers, but the image's non-root `mcp` user can't read the
root:docker (mode 660) socket.
**Fix:** run the service as root in compose (socket access is already
root-equivalent — no extra privilege gained):
```yaml
mcp-router:
  user: root
  volumes:
    - /var/run/docker.sock:/var/run/docker.sock:ro
```

---

## 11. `public/.htaccess: Permission denied` (writing on box)

**Cause:** file is root-owned. **Fix:** write via `sudo tee` (moot now — user is
root on the remote).

---

## 12. Git — wrong repo / `index.lock`

**Cause:** the git repo in scope was accidentally `C:\Users\tamal\.git` (home dir).
**Fix:** `git init` inside the project folder and commit there. (Be aware of the
stray home-dir `.git`.)

---

## 13. GitHub sync (remote ⇄ dev)

**Why:** the remote ran a **stale copy** — none of the Windows-side commits reached
it, which made every fix look like it "didn't take."
**Flow:**
1. Windows: `git remote add origin https://github.com/striketm98/AutoSecForge-V.2.git; git branch -M main`
2. **Create the empty repo** at https://github.com/new (no README/.gitignore/license).
   `git push` → "Repository not found" until the repo exists.
3. `git push -u origin main`
4. Remote `/opt/AutoSecForge-V.2`: `git pull` (or init+fetch+checkout), **preserving**
   `.env` / `public/.env`, then `docker compose up -d --build`.

---

## Quick diagnostics cheat-sheet

```bash
# what's running / health
docker compose ps
docker logs --tail 50 <container>

# Ollama
docker exec autosecforge-ollama ollama list
docker exec autosecforge-ollama ollama run qwen2.5:1.5b "hi"
docker exec autosecforge-ai-agent printenv OLLAMA_MODEL
free -h

# DB creds sanity
grep -E 'DB_(USER|PASSWORD|NAME|ROOT_PASSWORD)' .env public/.env

# network name (for one-off curl container)
docker network ls | grep asf

# rebuild one service after a fix
docker compose up -d --build <service>
```

---

## Known model fit (3.8 GB box)

| Model | Size | Fits 3.8 GB? |
| --- | --- | --- |
| `llama3` (8B) | 4.7 GB | ❌ OOM (`signal: killed`) |
| `qwen2.5:1.5b` | 986 MB | ✅ works |
| `qwen2.5:0.5b` | ~400 MB | ✅ fallback if 1.5b is tight |

_Last updated: 2026-06-13._
