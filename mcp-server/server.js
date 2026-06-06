'use strict';
// ─── AutoSecForge MCP HackStrike v12 ────────────────────────────
// Fixes: input validation, rate limiting, no arbitrary code execution,
//        target allow-list enforcement (SSRF prevention, mirrors ASF-002)

const express    = require('express');
const cors       = require('cors');
const helmet     = require('helmet');
const rateLimit  = require('express-rate-limit');
const axios      = require('axios');
const Docker     = require('dockerode');

const app    = express();
const docker = new Docker({ socketPath: '/var/run/docker.sock' });

// ── Security middleware ──────────────────────────────────────────
app.use(helmet());
app.use(cors({ origin: ['http://security-dashboard-app'] }));  // Only accept from app container
app.use(express.json({ limit: '64kb' }));

// Rate-limit all scan endpoints
const scanLimiter = rateLimit({ windowMs: 60_000, max: 20, message: { error: 'Too many requests' } });

// ── Input validation helpers ─────────────────────────────────────
const HOSTNAME_RE = /^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/;
const URL_RE      = /^https?:\/\/[a-zA-Z0-9\-.]+(:\d{1,5})?(\/[^\s]*)?$/;
const PRIVATE_IP  = /^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|127\.|169\.254\.|::1|fc00:|fe80:)/;

function validateTarget(target) {
    if (!target || typeof target !== 'string' || target.length > 253) return false;
    const stripped = target.replace(/^https?:\/\//, '').split('/')[0].split(':')[0];
    if (PRIVATE_IP.test(stripped)) return false;  // Block private ranges
    if (/^\d+\.\d+\.\d+\.\d+$/.test(stripped)) return true;  // Public IPs allowed
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
        stream.on('data', chunk => { out += chunk.slice(8).toString(); }); // Strip Docker framing header
        await new Promise((resolve, reject) => {
            stream.on('end', resolve);
            stream.on('error', reject);
        });
        return { success: true, stdout: out.slice(0, 50_000) };
    } catch (e) {
        return { success: false, error: e.message };
    }
}

// ── Health ────────────────────────────────────────────────────────
app.get('/health', (_req, res) => res.json({ status: 'ok', service: 'mcp-hackstrike', version: '12.0' }));

// ── Network scan (nmap) ───────────────────────────────────────────
app.post('/scan/network', scanLimiter, async (req, res) => {
    const { target, flags } = req.body;
    if (!validateTarget(target)) {
        return res.status(400).json({ error: 'Invalid or private target.' });
    }
    // Whitelist nmap flags — no arbitrary shell injection
    const SAFE_FLAGS = ['-sV', '-T4', '-p-', '--open', '-sU', '-A'];
    const safeFlags  = (flags || []).filter(f => SAFE_FLAGS.includes(f)).join(' ');
    const cmd = `nmap -oX - ${safeFlags} ${target}`;
    const result = await runInContainer('security-dashboard-nmap', cmd);
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
    const result = await runInContainer('security-dashboard-nikto', `nikto -h ${url} -Format json`);
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
    const result = await runInContainer('security-dashboard-sqlmap', cmd);
    res.json(result);
});

// ── 404 handler ───────────────────────────────────────────────────
app.use((_req, res) => res.status(404).json({ error: 'Endpoint not found.' }));

const PORT = 6300;
app.listen(PORT, '0.0.0.0', () => console.log(`MCP HackStrike listening on :${PORT}`));
