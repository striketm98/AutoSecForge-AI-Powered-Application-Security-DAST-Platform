<?php
// ============================================================
// AutoSecForge – helpers.php
// FIX ASF-005: session cookie flags set before session_start()
// FIX ASF-006: HTTP security response headers sent on every page
// FIX ASF-007: passwords hashed with Argon2ID (not SHA-256)
// FIX ASF-002: SSRF allowlist enforced in toolHealth() and
//              triggerScanFromUi(); loopback / RFC-1918 / link-local
//              addresses are blocked.
// ============================================================

// ============================================================
// ASF-005 FIX – Secure session cookie flags
// ============================================================
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',         // empty = current host only
    'secure'   => true,       // HTTPS only
    'httponly' => true,       // not accessible via JavaScript
    'samesite' => 'Strict',   // CSRF mitigation
]);
session_start();

// ============================================================
// ASF-006 FIX – HTTP security response headers
// ============================================================
function sendSecurityHeaders(): void
{
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    // Tighten the CSP to match your actual asset origins.
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self'; " .
        "style-src 'self' 'unsafe-inline'; " .   // allow inline CSS for now; tighten with nonces later
        "img-src 'self' data:; " .
        "font-src 'self'; " .
        "object-src 'none'; " .
        "frame-ancestors 'none';"
    );
}
sendSecurityHeaders();

// ============================================================
// ASF-007 FIX – Password helpers using Argon2ID
// ============================================================

/**
 * Hash a plaintext password.
 * Use this on registration / password change.
 */
function hashPassword(string $plaintext): string
{
    return password_hash($plaintext, PASSWORD_ARGON2ID);
}

/**
 * Verify a plaintext password against a stored hash.
 * Also transparently re-hashes legacy SHA-256 passwords on first
 * successful login so users are migrated without a forced reset.
 *
 * @param string  $plaintext    Password supplied by the user
 * @param string  $storedHash   Value from the database
 * @param int     $userId       For triggering a DB update on rehash
 * @return bool
 */
function verifyPassword(string $plaintext, string $storedHash, int $userId = 0): bool
{
    // Modern Argon2 hash
    if (str_starts_with($storedHash, '$argon')) {
        if (!password_verify($plaintext, $storedHash)) {
            return false;
        }
        // Rehash if the cost parameters have been upgraded
        if (password_needs_rehash($storedHash, PASSWORD_ARGON2ID)) {
            upgradePasswordHash($userId, hashPassword($plaintext));
        }
        return true;
    }

    // Legacy SHA-256 path (64-char hex) – migrate on successful login
    if (strlen($storedHash) === 64 && ctype_xdigit($storedHash)) {
        if (!hash_equals($storedHash, hash('sha256', $plaintext))) {
            return false;
        }
        // Upgrade to Argon2ID
        if ($userId > 0) {
            upgradePasswordHash($userId, hashPassword($plaintext));
        }
        return true;
    }

    return false;
}

function upgradePasswordHash(int $userId, string $newHash): void
{
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $userId]);
    } catch (Throwable $e) {
        error_log('Password rehash failed for user ' . $userId . ': ' . $e->getMessage());
    }
}

// ============================================================
// ASF-002 FIX – SSRF-safe outbound HTTP helper
// ============================================================

/**
 * Validate that a URL is safe for server-side fetching.
 * Blocks: loopback, RFC-1918, link-local, metadata endpoints.
 *
 * @throws InvalidArgumentException if the URL is not allowed
 */
function assertSafeUrl(string $url): void
{
    $parsed = parse_url($url);

    if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
        throw new InvalidArgumentException("Invalid URL: missing scheme or host.");
    }

    $scheme = strtolower($parsed['scheme']);
    if (!in_array($scheme, ['https', 'http'], true)) {
        throw new InvalidArgumentException("URL scheme '{$scheme}' is not permitted.");
    }

    $host = strtolower($parsed['host']);

    // Resolve to IP for address-range checks
    $ip = filter_var($host, FILTER_VALIDATE_IP)
        ? $host
        : gethostbyname($host);

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new InvalidArgumentException("Could not resolve host to a valid IP.");
    }

    // Block loopback
    if (in_array($ip, ['127.0.0.1', '::1'], true) || str_starts_with($ip, '127.')) {
        throw new InvalidArgumentException("Loopback addresses are not permitted.");
    }

    // Block AWS/GCP metadata endpoint
    if (str_starts_with($ip, '169.254.')) {
        throw new InvalidArgumentException("Link-local addresses are not permitted.");
    }

    // Block RFC-1918 private ranges
    $privateRanges = [
        ['10.0.0.0',     'AF'],     // 10/8
        ['172.16.0.0',   'AC10'],   // 172.16/12
        ['192.168.0.0',  'C0A8'],   // 192.168/16
    ];
    $ipLong = ip2long($ip);
    if ($ipLong !== false) {
        if (
            ($ipLong >= ip2long('10.0.0.0')    && $ipLong <= ip2long('10.255.255.255'))    ||
            ($ipLong >= ip2long('172.16.0.0')   && $ipLong <= ip2long('172.31.255.255'))   ||
            ($ipLong >= ip2long('192.168.0.0')  && $ipLong <= ip2long('192.168.255.255'))
        ) {
            throw new InvalidArgumentException("Private/internal IP ranges are not permitted.");
        }
    }
}

/**
 * Perform a safe outbound HTTP GET using cURL.
 * Enforces SSRF allowlist via assertSafeUrl() before making any request.
 */
function safeFetch(string $url, int $timeoutSeconds = 10): string|false
{
    assertSafeUrl($url);   // throws on disallowed URLs

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeoutSeconds,
        CURLOPT_FOLLOWLOCATION => false,        // prevent redirect-based bypass
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'AutoSecForge/1.0',
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0 || $response === false) {
        return false;
    }
    return $response;
}

/**
 * Check the health of a configured tool endpoint.
 * ASF-002 FIX: replaced raw file_get_contents() with safeFetch().
 */
function toolHealth(string $endpointUrl): array
{
    try {
        $healthUrl = rtrim($endpointUrl, '/') . '/health';
        $body = safeFetch($healthUrl, 5);
        if ($body === false) {
            return ['status' => 'unreachable', 'detail' => 'Connection failed'];
        }
        return ['status' => 'ok', 'detail' => substr($body, 0, 512)];
    } catch (InvalidArgumentException $e) {
        error_log('SSRF attempt blocked in toolHealth(): ' . $e->getMessage());
        return ['status' => 'error', 'detail' => 'Endpoint URL is not permitted.'];
    }
}

/**
 * Trigger a scan from the UI.
 * ASF-002 FIX: replaced raw file_get_contents() with safeFetch().
 */
function triggerScanFromUi(string $apiBaseUrl, array $params = []): array
{
    try {
        $triggerUrl = rtrim($apiBaseUrl, '/') . '/scan?' . http_build_query($params);
        $body = safeFetch($triggerUrl, 30);
        if ($body === false) {
            return ['success' => false, 'message' => 'Scan trigger failed – endpoint unreachable.'];
        }
        $decoded = json_decode($body, true);
        return $decoded ?? ['success' => true, 'raw' => substr($body, 0, 512)];
    } catch (InvalidArgumentException $e) {
        error_log('SSRF attempt blocked in triggerScanFromUi(): ' . $e->getMessage());
        return ['success' => false, 'message' => 'API base URL is not permitted.'];
    }
}

// ============================================================
// Output escaping helper (unchanged – already safe)
// ============================================================
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ============================================================
// CSRF helpers
// ============================================================
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// Auth guard
// ============================================================
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * ASF-007 FIX: Updated login to use verifyPassword() with Argon2ID
 * and transparent SHA-256 → Argon2ID migration.
 */
function loginAttempt(string $email, string $password): bool
{
    try {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user) {
            // Timing-safe dummy check to prevent user enumeration
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$dummy$dummy');
            return false;
        }

        // Accepts both Argon2ID and legacy SHA-256; migrates on success
        if (!verifyPassword($password, $user['password_hash'], (int)$user['id'])) {
            return false;
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        return true;

    } catch (Throwable $e) {
        error_log('loginAttempt error: ' . $e->getMessage());
        return false;
    }
}
