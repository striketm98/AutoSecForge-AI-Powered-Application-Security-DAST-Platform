'use strict';
// ─── AutoSecForge MCP HackStrike v12.1 ──────────────────────────
// Added: /scan/security-review (Ollama-backed triage orchestration)

const express    = require('express');
const cors       = require('cors');
const helmet     = require('helmet');
const rateLimit  = require('express-rate-limit');
const axios      = require('axios');
const Docker     = require('dockerode');

const app    = express();
const docker = new Docker({ socketPath: '/var/run/docker.sock' });

const AI_AGENT_URL = process.env.AI_AGENT_URL || 'http://ai-agent:6400';
const ZAP_URL      = process.env.ZAP_URL      || 'http://zap:8090';
const ZAP_API_KEY  = process.env.ZAP_API_KEY  || '';
const SONAR_HOST   = process.env.SONAR_HOST_URL || 'http://sonarqube:9000';
const SONAR_TOKEN  = process.env.SONAR_TOKEN    || '';

const sleep = ms => new Promise(r => setTimeout(r, ms));

// ── Security middleware ──────────────────────────────────────────
app.use(helmet());
app.use(cors({ origin: ['http://security-dashboard-app'] }));
app.use(express.json({ limit: '128kb' }));

const scanLimiter = rateLimit({ windowMs: 60_000, max: 20, message: { error: 'Too many requests' } });

// ── Input validation helpers ─────────────────────────────────────
const HOSTNAME_RE = /^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/;
const URL_RE      = /^https?:\/\/[a-zA-Z0-9\-.]+(:\d{1,5})?(\/[^\s]*)?$/;
const PRIVATE_IP  = /^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|127\.|169\.254\.|::1|fc00:|fe80:)/;

function validateTarget(target) {
    if (!target || typeof target !== 'string' || target.length > 253) return false;
    const stripped = target.replace(/^https?:\/\//, '').split('/')[0].split(':')[0];
    if (PRIVATE_IP.test(stripped)) return false;
    if (/^\d+\.\d+\.\d+\.\d+$/.test(stripped)) return true;
    return HOSTNAME_RE.test(stripped);
}

// ── Run command inside named container ───────────────────────────
async function runInContainer(containerName, cmd) {
    try {
        const c = docker.getContainer(containerName);
        const exec = await c.exec({
            Cmd: ['sh', '-c', cmd],
            AttachStdout: true,
            AttachStderr: true,
        });
        const stream = await exec.start();
        let out = '';
        stream.on('data', chunk => { out += chunk.slice(8).toString(); });
        await new Promise((resolve, reject) => {
            stream.on('end', resolve);
            stream.on('error', reject);
        });
        return { success: true, stdout: out.slice(0, 50_000) };
    } catch (e) {
        return { success: false, error: e.message };
    }
}

// ── Call AI agent for triage ─────────────────────────────────────
async function aiTriage(target, scanType, rawOutput) {
    try {
        const resp = await axios.post(`${AI_AGENT_URL}/v1/security-review`, {
            target,
            scan_type: scanType,
            raw_output: rawOutput,
        }, { timeout: 180_000 });
        return resp.data;
    } catch (e) {
        return { ok: false, analysis: `AI triage unavailable: ${e.message}` };
    }
}

// ── OWASP ZAP (DAST via REST API) ────────────────────────────────
async function zapGet(path, params = {}) {
    const resp = await axios.get(`${ZAP_URL}${path}`, {
        params: { apikey: ZAP_API_KEY, ...params },
        timeout: 20_000,
    });
    return resp.data;
}

/**
 * Run a bounded ZAP spider + active scan and collect alerts.
 * Polling is time-capped so the orchestrated review never hangs the request.
 */
async function runZap(url) {
    try {
        // 1) Spider (cap ~60s)
        const spider = await zapGet('/JSON/spider/action/scan/', { url, maxChildren: 10 });
        const spiderId = spider.scan;
        for (let i = 0; i < 30; i++) {
            const s = await zapGet('/JSON/spider/view/status/', { scanId: spiderId });
            if (parseInt(s.status) >= 100) break;
            await sleep(2000);
        }

        // 2) Active scan (cap ~90s) — best effort, alerts collected regardless
        let ascanId = null;
        try {
            const ascan = await zapGet('/JSON/ascan/action/scan/', { url, recurse: true, inScopeOnly: false });
            ascanId = ascan.scan;
            for (let i = 0; i < 45; i++) {
                const s = await zapGet('/JSON/ascan/view/status/', { scanId: ascanId });
                if (parseInt(s.status) >= 100) break;
                await sleep(2000);
            }
        } catch (_e) { /* active scan may be disabled — keep passive alerts */ }

        // 3) Collect alerts
        const data   = await zapGet('/JSON/alert/view/alerts/', { baseurl: url, start: 0, count: 200 });
        const alerts = data.alerts || [];

        if (!alerts.length) {
            return { success: true, stdout: `ZAP scan complete for ${url}. No alerts reported.` };
        }
        const lines = alerts.slice(0, 200).map(a =>
            `[${(a.risk || 'Info').toUpperCase()}] ${a.alert} | url=${a.url} | CWE-${a.cweid || '?'} | confidence=${a.confidence} | ${(a.description || '').slice(0, 200)}`
        );
        return { success: true, stdout: `=== ZAP ALERTS (${alerts.length}) for ${url} ===\n` + lines.join('\n') };
    } catch (e) {
        return { success: false, error: `ZAP unreachable or failed: ${e.message}` };
    }
}

/**
 * Authenticated ZAP scan. Sets up a context with form-based authentication,
 * creates the user with the supplied credentials, then spiders + active-scans
 * AS that user so everything behind the login is exercised.
 *
 *   auth = { loginUrl, username, password,
 *            usernameField?, passwordField?, loggedInRegex? }
 */
async function runZapAuth(url, auth) {
    try {
        const ctxName = 'asf_' + Date.now();

        // 1) Context + scope
        const ctx       = await zapGet('/JSON/context/action/newContext/', { contextName: ctxName });
        const contextId = ctx.contextId;
        const m         = url.match(/^(https?:\/\/[^/]+)/);
        const origin    = m ? m[1] : url;
        const includeRe = origin.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '.*';
        await zapGet('/JSON/context/action/includeInContext/', { contextName: ctxName, regex: includeRe });

        // 2) Form-based authentication. The inner key/value pairs are
        //    URL-encoded once here; axios encodes the outer layer — ZAP peels
        //    both back to recover loginUrl and the %username%/%password% template.
        const uField    = auth.usernameField || 'username';
        const pField    = auth.passwordField || 'password';
        const loginData = `${uField}=%username%&${pField}=%password%`;
        const cfg       = 'loginUrl=' + encodeURIComponent(auth.loginUrl) +
                          '&loginRequestData=' + encodeURIComponent(loginData);
        await zapGet('/JSON/authentication/action/setAuthenticationMethod/', {
            contextId, authMethodName: 'formBasedAuthentication', authMethodConfigParams: cfg,
        });
        if (auth.loggedInRegex) {
            await zapGet('/JSON/authentication/action/setLoggedInIndicator/', {
                contextId, loggedInIndicatorRegex: auth.loggedInRegex,
            }).catch(() => {});
        }

        // 3) User + credentials
        const u      = await zapGet('/JSON/users/action/newUser/', { contextId, name: 'asfuser' });
        const userId = u.userId;
        const creds  = 'username=' + encodeURIComponent(auth.username) +
                       '&password=' + encodeURIComponent(auth.password);
        await zapGet('/JSON/users/action/setAuthenticationCredentials/', {
            contextId, userId, authCredentialsConfigParams: creds,
        });
        await zapGet('/JSON/users/action/setUserEnabled/', { contextId, userId, enabled: true });
        await zapGet('/JSON/forcedUser/action/setForcedUser/',     { contextId, userId }).catch(() => {});
        await zapGet('/JSON/forcedUser/action/setForcedUserModeEnabled/', { boolean: true }).catch(() => {});

        // 4) Spider as user (cap ~60s)
        const spider   = await zapGet('/JSON/spider/action/scanAsUser/', {
            contextId, userId, url, maxChildren: 10, recurse: true });
        const spiderId = spider.scanAsUser ?? spider.scan;
        for (let i = 0; i < 30; i++) {
            const s = await zapGet('/JSON/spider/view/status/', { scanId: spiderId });
            if (parseInt(s.status) >= 100) break;
            await sleep(2000);
        }

        // 5) Active scan as user (cap ~90s) — best effort
        try {
            const a       = await zapGet('/JSON/ascan/action/scanAsUser/', {
                url, contextId, userId, recurse: true });
            const ascanId = a.scanAsUser ?? a.scan;
            for (let i = 0; i < 45; i++) {
                const s = await zapGet('/JSON/ascan/view/status/', { scanId: ascanId });
                if (parseInt(s.status) >= 100) break;
                await sleep(2000);
            }
        } catch (_e) { /* keep whatever alerts we have */ }

        // 6) Collect alerts
        const data   = await zapGet('/JSON/alert/view/alerts/', { baseurl: url, start: 0, count: 200 });
        const alerts = data.alerts || [];
        const head   = `=== ZAP AUTHENTICATED ALERTS (${alerts.length}) for ${url} (user=${auth.username}) ===`;
        if (!alerts.length) return { success: true, stdout: `${head}\nNo alerts reported.` };
        const lines = alerts.slice(0, 200).map(a =>
            `[${(a.risk || 'Info').toUpperCase()}] ${a.alert} | url=${a.url} | CWE-${a.cweid || '?'} | confidence=${a.confidence} | ${(a.description || '').slice(0, 200)}`
        );
        return { success: true, stdout: `${head}\n` + lines.join('\n') };
    } catch (e) {
        return { success: false, error: `Authenticated ZAP failed: ${e.message}` };
    }
}

// ── Trivy (container/filesystem SCA) ─────────────────────────────
// Image names don't fit the host-target validator, so guard separately.
const IMAGE_RE = /^[a-zA-Z0-9][a-zA-Z0-9._\/-]{0,200}(:[a-zA-Z0-9._-]{1,128})?(@sha256:[a-f0-9]{64})?$/;

async function runTrivy(image) {
    // Standalone scan inside the trivy container; --quiet keeps stdout clean.
    const cmd = `trivy image --scanners vuln --quiet --no-progress --severity CRITICAL,HIGH,MEDIUM ${image}`;
    return runInContainer('autosecforge-trivy', cmd);
}

// ── Health ────────────────────────────────────────────────────────
app.get('/health', (_req, res) => res.json({ status: 'ok', service: 'mcp-hackstrike', version: '12.1' }));

// ── Network scan (nmap) ───────────────────────────────────────────
app.post('/scan/network', scanLimiter, async (req, res) => {
    const { target, flags } = req.body;
    if (!validateTarget(target)) {
        return res.status(400).json({ error: 'Invalid or private target.' });
    }
    const SAFE_FLAGS = ['-sV', '-T4', '-p-', '--open', '-sU', '-A'];
    const safeFlags  = (flags || []).filter(f => SAFE_FLAGS.includes(f)).join(' ');
    const cmd = `nmap -oX - ${safeFlags} ${target}`;
    const result = await runInContainer('autosecforge-nmap', cmd);
    res.json(result);
});

// ── DAST scan (nikto) ─────────────────────────────────────────────
app.post('/scan/dast', scanLimiter, async (req, res) => {
    const { url } = req.body;
    if (!url || !URL_RE.test(url)) {
        return res.status(400).json({ error: 'Invalid URL.' });
    }
    const stripped = url.replace(/^https?:\/\//, '').split('/')[0].split(':')[0];
    if (PRIVATE_IP.test(stripped)) {
        return res.status(400).json({ error: 'Internal URLs are not permitted.' });
    }
    const result = await runInContainer('autosecforge-nikto', `nikto -h ${url} -Format json`);
    res.json(result);
});

// ── SQL injection (sqlmap) ────────────────────────────────────────
app.post('/scan/sqli', scanLimiter, async (req, res) => {
    const { url, level } = req.body;
    if (!url || !URL_RE.test(url)) {
        return res.status(400).json({ error: 'Invalid URL.' });
    }
    const stripped = url.replace(/^https?:\/\//, '').split('/')[0];
    if (PRIVATE_IP.test(stripped)) {
        return res.status(400).json({ error: 'Internal URLs are not permitted.' });
    }
    const safeLevel = Math.min(Math.max(parseInt(level) || 1, 1), 5);
    const cmd = `sqlmap -u "${url}" --level=${safeLevel} --batch --output-dir=/tmp/sqlmap --format=json`;
    const result = await runInContainer('autosecforge-sqlmap', cmd);
    res.json(result);
});

// ── Security Review (orchestrated: nmap + nikto → Ollama triage) ──
app.post('/scan/security-review', scanLimiter, async (req, res) => {
    const { target, scan_types, zap_auth } = req.body;

    if (!validateTarget(target)) {
        return res.status(400).json({ error: 'Invalid or private target.' });
    }

    // Optional authenticated-ZAP credentials. Validate the login URL the same
    // way as any other target (public, well-formed) before trusting it.
    let zapAuth = null;
    if (zap_auth && typeof zap_auth === 'object'
        && zap_auth.loginUrl && zap_auth.username && zap_auth.password) {
        const lh = String(zap_auth.loginUrl).replace(/^https?:\/\//, '').split('/')[0].split(':')[0];
        if (URL_RE.test(zap_auth.loginUrl) && !PRIVATE_IP.test(lh)) {
            zapAuth = {
                loginUrl:      String(zap_auth.loginUrl),
                username:      String(zap_auth.username),
                password:      String(zap_auth.password),
                usernameField: zap_auth.usernameField ? String(zap_auth.usernameField) : 'username',
                passwordField: zap_auth.passwordField ? String(zap_auth.passwordField) : 'password',
                loggedInRegex: zap_auth.loggedInRegex ? String(zap_auth.loggedInRegex) : '',
            };
        }
    }

    const types = Array.isArray(scan_types) && scan_types.length
        ? scan_types
        : ['network'];

    const ALLOWED_TYPES = new Set(['network', 'dast', 'sqli', 'zap']);
    const validTypes = types.filter(t => ALLOWED_TYPES.has(t));
    if (!validTypes.length) {
        return res.status(400).json({ error: 'No valid scan types specified. Allowed: network, dast, sqli, zap' });
    }

    const rawParts   = [];
    const scanErrors = [];

    // Run each scan sequentially to avoid overwhelming the containers
    for (const type of validTypes) {
        let result;
        switch (type) {
            case 'network': {
                const cmd = `nmap -sV -T4 --open ${target}`;
                result = await runInContainer('autosecforge-nmap', cmd);
                rawParts.push(`=== NMAP (network) ===\n${result.stdout || result.error}`);
                break;
            }
            case 'dast': {
                const url = target.startsWith('http') ? target : `http://${target}`;
                result = await runInContainer('autosecforge-nikto', `nikto -h ${url} -Format json`);
                rawParts.push(`=== NIKTO (dast) ===\n${result.stdout || result.error}`);
                break;
            }
            case 'sqli': {
                const url = target.startsWith('http') ? target : `http://${target}`;
                const cmd = `sqlmap -u "${url}" --level=1 --batch --output-dir=/tmp/sqlmap --format=json`;
                result = await runInContainer('autosecforge-sqlmap', cmd);
                rawParts.push(`=== SQLMAP (sqli) ===\n${result.stdout || result.error}`);
                break;
            }
            case 'zap': {
                const url = target.startsWith('http') ? target : `http://${target}`;
                result = zapAuth ? await runZapAuth(url, zapAuth) : await runZap(url);
                rawParts.push(`=== OWASP ZAP (${zapAuth ? 'authenticated ' : ''}dast) ===\n${result.stdout || result.error}`);
                break;
            }
        }
        if (!result.success) {
            scanErrors.push(`${type}: ${result.error}`);
        }
    }

    const rawOutput = rawParts.join('\n\n');

    // AI triage via Ollama
    const triage = await aiTriage(target, validTypes.join('+'), rawOutput);

    res.json({
        target,
        scan_types: validTypes,
        raw_output: rawOutput,
        scan_errors: scanErrors,
        analysis: triage.analysis || triage.content || '',
        findings: Array.isArray(triage.findings) ? triage.findings : [],
        model: triage.model || 'unknown',
        timestamp: new Date().toISOString(),
        ok: triage.ok !== false,
    });
});

// ── Container / image SCA (Trivy → Ollama triage) ────────────────
app.post('/scan/container', scanLimiter, async (req, res) => {
    const { image } = req.body;
    if (!image || typeof image !== 'string' || !IMAGE_RE.test(image.trim())) {
        return res.status(400).json({ error: 'Invalid image reference. Example: nginx:1.25 or alpine@sha256:…' });
    }
    const img    = image.trim();
    const result = await runTrivy(img);
    const raw    = `=== TRIVY (container SCA) for ${img} ===\n${result.stdout || result.error}`;
    const triage = await aiTriage(img, 'container', raw);

    res.json({
        target:      img,
        scan_types:  ['container'],
        raw_output:  raw,
        scan_errors: result.success ? [] : [`trivy: ${result.error}`],
        analysis:    triage.analysis || triage.content || '',
        findings:    Array.isArray(triage.findings) ? triage.findings : [],
        model:       triage.model || 'unknown',
        timestamp:   new Date().toISOString(),
        ok:          triage.ok !== false,
    });
});

// ── SAST (SonarQube via sonar-scanner-cli → Ollama triage) ───────
const SAFE_ID = /^[a-zA-Z0-9_-]{1,80}$/;

app.post('/scan/sast', scanLimiter, async (req, res) => {
    const { project, base_dir } = req.body;
    if (!project || !SAFE_ID.test(project) || !base_dir || !SAFE_ID.test(base_dir)) {
        return res.status(400).json({ error: 'Invalid project or base_dir (allowed: a-z A-Z 0-9 _ -).' });
    }
    if (!SONAR_TOKEN) {
        return res.status(400).json({ error: 'SONAR_TOKEN is not configured. Create a token in SonarQube and set it in .env.' });
    }

    // 1) Run the scanner inside the CLI container against the shared source tree.
    const srcPath = `/usr/src/${base_dir}`;
    const scanCmd =
        `cd ${srcPath} && sonar-scanner` +
        ` -Dsonar.projectKey=${project}` +
        ` -Dsonar.sources=.` +
        ` -Dsonar.host.url=${SONAR_HOST}` +
        ` -Dsonar.token=${SONAR_TOKEN}` +
        ` -Dsonar.scm.disabled=true` +
        ` -Dsonar.projectBaseDir=${srcPath}`;
    const scan = await runInContainer('autosecforge-sonar-scanner', scanCmd);
    if (!scan.success) {
        return res.json({ error: `sonar-scanner failed: ${scan.error}` });
    }

    // 2) Wait for the Compute Engine to finish analysing (cap ~90s).
    const auth = { headers: { Authorization: `Bearer ${SONAR_TOKEN}` }, timeout: 15_000 };
    try {
        for (let i = 0; i < 30; i++) {
            await sleep(3000);
            const act = await axios.get(`${SONAR_HOST}/api/ce/activity?component=${project}&ps=1`, auth);
            const task = (act.data.tasks || [])[0];
            if (task && ['SUCCESS', 'FAILED', 'CANCELED'].includes(task.status)) break;
        }
    } catch (_e) { /* fall through to issue fetch */ }

    // 3) Fetch issues.
    let issues = [];
    try {
        const r = await axios.get(
            `${SONAR_HOST}/api/issues/search?componentKeys=${project}&ps=200&resolved=false&types=VULNERABILITY,BUG,CODE_SMELL`,
            auth);
        issues = r.data.issues || [];
    } catch (e) {
        return res.json({ error: `SonarQube issue fetch failed: ${e.message}`, scanner_log: scan.stdout });
    }

    // SonarQube severity → our scale
    const sevMap = { BLOCKER: 'critical', CRITICAL: 'high', MAJOR: 'medium', MINOR: 'low', INFO: 'info' };
    const findings = issues.slice(0, 200).map(it => ({
        title:        (it.message || it.rule || 'SAST issue').slice(0, 500),
        severity:     sevMap[it.severity] || 'medium',
        description:  `Rule ${it.rule || ''} · type ${it.type || ''}`,
        affected_url: it.component ? `${it.component}${it.line ? ':' + it.line : ''}` : project,
        cwe_id: '', cve_id: '', remediation: '',
    }));

    const counts = findings.reduce((a, f) => (a[f.severity] = (a[f.severity] || 0) + 1, a), {});
    let raw = `=== SONARQUBE SAST for ${project} ===\nIssues: ${issues.length}\n` +
        `Severity: ${JSON.stringify(counts)}\n\n` +
        findings.map(f => `[${f.severity.toUpperCase()}] ${f.title} (${f.affected_url})`).join('\n');
    raw = raw.slice(0, 11000);

    const triage = await aiTriage(project, 'sast', raw);

    res.json({
        target:      project,
        scan_types:  ['sast'],
        raw_output:  raw,
        scan_errors: [],
        analysis:    triage.analysis || triage.content || '',
        findings,                     // authoritative SonarQube findings
        model:       triage.model || 'unknown',
        timestamp:   new Date().toISOString(),
        ok:          true,
    });
});

// ── 404 handler ───────────────────────────────────────────────────
app.use((_req, res) => res.status(404).json({ error: 'Endpoint not found.' }));

const PORT = 6300;
app.listen(PORT, '0.0.0.0', () => console.log(`MCP HackStrike listening on :${PORT}`));
