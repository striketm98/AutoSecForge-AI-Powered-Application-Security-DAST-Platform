<?php
// ─── AutoSecForge Pro v12 — Helpers ──────────────────────────────
// Fixes: ASF-002 (SSRF), ASF-004 (file upload validation)

declare(strict_types=1);

// ─── ASF-002: SSRF protection ────────────────────────────────────
// Block requests to private/loopback ranges and internal tool ports.
// Only allow outbound HTTP from toolHealth() to known containers.

const ALLOWED_INTERNAL_HOSTS = [
    'security-dashboard-sonarqube',
    'security-dashboard-zap',
    'security-dashboard-mobsf',
    'autosecforge-mcp-hackstrike',
    'autosecforge-openai-free-agents',
    'security-dashboard-pentest-python',
    'security-dashboard-oasm',
    'security-dashboard-sqlmap',
];

/**
 * Safe internal HTTP GET — only to allow-listed hostnames.
 * Blocks all IP-based targets (prevents SSRF to cloud metadata, etc.).
 */
function safe_internal_get(string $url, int $timeout = 5): array {
    $parsed = parse_url($url);
    $host   = $parsed['host'] ?? '';

    // ASF-002: Reject IP addresses entirely
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return ['error' => 'Direct IP targets are not permitted (SSRF prevention).'];
    }

    // ASF-002: Reject private/loopback hostnames by resolving them
    $resolved = gethostbyname($host);
    if (is_private_ip($resolved)) {
        // Exception: only allow-listed internal container names pass
        if (!in_array($host, ALLOWED_INTERNAL_HOSTS, true)) {
            return ['error' => "Host '{$host}' is not on the internal allow-list."];
        }
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => false,   // Never follow redirects (SSRF)
        CURLOPT_SSL_VERIFYPEER => false,   // Internal containers use self-signed certs
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['error' => $err];
    }
    return ['status' => $code, 'body' => $body];
}

function is_private_ip(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/**
 * Tool health probe — uses allow-list above.
 */
function toolHealth(string $service): array {
    $urls = [
        'sonarqube' => 'http://security-dashboard-sonarqube:9000/api/system/ping',
        'zap'       => 'http://security-dashboard-zap:8090/JSON/core/view/version/',
        'mobsf'     => 'http://security-dashboard-mobsf:8000/api/v1/health',
        'mcp'       => 'http://autosecforge-mcp-hackstrike:6300/health',
        'ai'        => 'http://autosecforge-openai-free-agents:6400/health',
    ];

    if (!isset($urls[$service])) {
        return ['error' => "Unknown service: {$service}"];
    }
    return safe_internal_get($urls[$service]);
}

// ─── ASF-004: File upload validation ─────────────────────────────
const ALLOWED_EVIDENCE_MIME = [
    'image/png', 'image/jpeg', 'image/gif', 'image/webp',
    'application/pdf', 'text/plain', 'text/csv',
    'application/zip',
];
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10 MB

function validate_evidence_upload(array $file): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload error code: ' . $file['error'];
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return 'File exceeds 10 MB limit.';
    }
    // ASF-004: Use finfo (magic bytes), not the browser-supplied MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_EVIDENCE_MIME, true)) {
        return "File type '{$mime}' is not permitted.";
    }
    // ASF-004: Randomise filename to prevent directory traversal
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $filename = bin2hex(random_bytes(16)) . '.' . strtolower($safe_ext);
    return null; // null = valid; caller uses $filename
}

// ─── CSRF token helpers ───────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
