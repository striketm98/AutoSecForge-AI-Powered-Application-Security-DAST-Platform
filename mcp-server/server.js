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
    const { target, scan_types } = req.body;

    if (!validateTarget(target)) {
        return res.status(400).json({ error: 'Invalid or private target.' });
    }

    const types = Array.isArray(scan_types) && scan_types.length
        ? scan_types
        : ['network'];

    const ALLOWED_TYPES = new Set(['network', 'dast', 'sqli']);
    const validTypes = types.filter(t => ALLOWED_TYPES.has(t));
    if (!validTypes.length) {
        return res.status(400).json({ error: 'No valid scan types specified. Allowed: network, dast, sqli' });
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

// ── 404 handler ───────────────────────────────────────────────────
app.use((_req, res) => res.status(404).json({ error: 'Endpoint not found.' }));

const PORT = 6300;
app.listen(PORT, '0.0.0.0', () => console.log(`MCP HackStrike listening on :${PORT}`));
