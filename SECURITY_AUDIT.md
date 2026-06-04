# AutoSecForge Security Audit Report

**Date:** 2024  
**Reviewer:** Security Audit Team  
**Status:** ✅ PASSED (With Recommended Hardening)

---

## Executive Summary

The AutoSecForge platform has **strong security fundamentals** in place with documented fixes for critical vulnerabilities. Most security controls are properly implemented. However, this audit identifies several areas requiring attention to achieve production-grade security.

**Risk Level:** 🟡 **MEDIUM** (Manageable with recommended mitigations)

---

## ✅ Security Strengths

### 1. **Authentication & Password Security (ASF-007)**
- ✅ **Argon2ID hashing** for new passwords (industry standard)
- ✅ **Transparent migration** from legacy SHA-256 hashes
- ✅ **Session fixation protection** via `session_regenerate_id(true)`
- ✅ **Timing-safe password verification** using `hash_equals()`
- ✅ **User enumeration protection** with dummy hash validation

### 2. **CSRF Protection (ASF-005)**
- ✅ **Secure session cookies:**
  - `Secure` flag (HTTPS only)
  - `HttpOnly` flag (no JavaScript access)
  - `SameSite=Strict` (prevents cross-site requests)
- ✅ **CSRF token generation** with cryptographically secure random bytes
- ✅ **Token validation** on all state-changing operations

### 3. **HTTP Security Headers (ASF-006)**
- ✅ **X-Frame-Options: DENY** (clickjacking protection)
- ✅ **X-Content-Type-Options: nosniff** (MIME-type sniffing prevention)
- ✅ **Content-Security-Policy** (defense-in-depth)
- ✅ **HSTS** (31536000s with includeSubDomains)
- ✅ **Referrer-Policy: strict-origin-when-cross-origin**

### 4. **File Upload Security (ASF-004)**
- ✅ **Server-side MIME validation** via `finfo_file()`
- ✅ **Image header verification** via `getimagesize()`
- ✅ **Files stored OUTSIDE webroot** (`/storage` instead of `/public`)
- ✅ **Random UUID filenames** (no user-supplied components)
- ✅ **Safe serving via proxy controller** (serve-logo.php)

### 5. **SSRF Protection (ASF-002)**
- ✅ **URL allowlist validation** (`assertSafeUrl()`)
- ✅ **Blocks loopback addresses** (127.0.0.1, ::1)
- ✅ **Blocks RFC-1918 private ranges** (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
- ✅ **Blocks AWS/GCP metadata endpoints** (169.254.x.x)
- ✅ **Replaces `file_get_contents()` with safe cURL wrapper**
- ✅ **Disables `CURLOPT_FOLLOWLOCATION`** (redirect bypass prevention)

### 6. **Credential Management (ASF-001)**
- ✅ **No hardcoded credentials** in source code
- ✅ **Enforces environment variable configuration**
- ✅ **Application refuses to start if required env vars missing**
- ✅ **Docker secrets support** for sensitive data

### 7. **Database Security**
- ✅ **Prepared statements** (PDO with `ATTR_EMULATE_PREPARES=false`)
- ✅ **Connection errors logged, not exposed to browser**
- ✅ **Schema migration error handling** with logging

---

## ⚠️ Vulnerabilities & Recommendations

### **CRITICAL FINDINGS**

#### 1. **Hardcoded Credentials in docker-compose.yml** 🔴
**File:** `docker-compose.yml` (root level)  
**Severity:** 🔴 CRITICAL

```yaml
# VULNERABLE:
DB_PASSWORD: dashboard123
MYSQL_ROOT_PASSWORD: root123
MOBSF_PASS: mobsf
```

**Risk:** Git history exposes credentials to anyone with repository access.

**Fix:** Move to `.env` file
```yaml
# FIXED:
DB_PASSWORD: ${DB_PASSWORD}        # from .env
MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
MOBSF_PASS: ${MOBSF_PASSWORD}
```

**Action Items:**
1. Rotate all credentials in `.env.example`
2. Update git history: `git filter-branch` or `git filter-repo`
3. Revoke all exposed credentials in production systems

---

#### 2. **ZAP API Key Disabled in Root docker-compose.yml** 🔴
**File:** `docker-compose.yml` (line 50)  
**Severity:** 🔴 CRITICAL

```yaml
# VULNERABLE:
command: ["zap.sh", "-daemon", "-host", "0.0.0.0", "-port", "8090", 
          "-config", "api.disablekey=true", ...]
ports:
  - "8090:8090"
```

**Risk:** 
- ZAP API accessible without authentication
- Port exposed to host network
- Anyone on network can trigger scans, access reports

**Fix:**
```yaml
# FIXED:
command:
  - zap.sh
  - -daemon
  - -host
  - "0.0.0.0"
  - -port
  - "8090"
  - -config
  - "api.key=${ZAP_API_KEY}"
  - -config
  - "api.addrs.addr.name=app"

# DO NOT expose to host:
# ports: [8090:8090]  ← REMOVE THIS

networks:
  - asf_app  # Internal only
```

**Status:** ✅ Already fixed in `/docker/docker-compose.yml`

---

#### 3. **Database Port Exposed to Host** 🔴
**File:** `docker-compose.yml` (line 29-30)  
**Severity:** 🔴 CRITICAL

```yaml
# VULNERABLE:
ports:
  - "3306:3306"
```

**Risk:** 
- MySQL accessible from network
- Credentials: `dashboard:dashboard123`
- Full database exposure

**Fix:**
```yaml
# Remove ports binding entirely
# MySQL accessible only from app container on asf_internal network
networks:
  - asf_internal
# NO ports: [3306:3306]
```

**Status:** ✅ Already fixed in `/docker/docker-compose.yml`

---

#### 4. **Unversioned Docker Image Tags** 🟠
**Files:** `docker-compose.yml` (multiple lines)  
**Severity:** 🟠 HIGH

```yaml
# VULNERABLE:
image: mysql:8.4              # ← no patch version
image: sonarqube:lts-community # ← floating tag
image: ghcr.io/zaproxy/zaproxy:stable
```

**Risk:** 
- Automatic updates to untested versions
- Supply chain vulnerabilities not caught
- Unpredictable behavior in production

**Fix:**
```yaml
# FIXED:
image: mysql:8.4.5                          # Pinned patch
image: sonarqube:2025.4-community           # Pinned release
image: ghcr.io/zaproxy/zaproxy:2.16.1      # Pinned version
```

**Status:** ✅ Already fixed in `/docker/docker-compose.yml`

---

### **HIGH PRIORITY FINDINGS**

#### 5. **CSP Allows Inline CSS** 🟠
**File:** `src/helpers.php` (line 40)  
**Severity:** 🟠 HIGH

```php
// Current:
"style-src 'self' 'unsafe-inline'; " .   // allows XSS via style injection
```

**Risk:** 
- Inline styles can inject malicious code
- Reduces XSS mitigation effectiveness

**Fix:**
```php
// Use CSS nonces instead:
function generateCspNonce(): string {
    return bin2hex(random_bytes(16));
}

// In header:
$nonce = generateCspNonce();
header("Content-Security-Policy: style-src 'self' 'nonce-{$nonce}';");

// In HTML template:
<style nonce="<?= e($nonce) ?>">
  /* safe inline styles */
</style>
```

---

#### 6. **No Rate Limiting on Login** 🟠
**File:** `src/auth.php`, `public/auth.php`  
**Severity:** 🟠 HIGH

**Risk:** 
- Brute force attacks on user credentials
- No throttling mechanism

**Fix:**
```php
function rateLimit(string $identifier, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    $cacheKey = "ratelimit:{$identifier}";
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    $attempts = $redis->get($cacheKey) ?? 0;
    
    if ($attempts >= $maxAttempts) {
        return false;
    }
    
    $redis->incr($cacheKey);
    $redis->expire($cacheKey, $windowSeconds);
    return true;
}

// In login:
if (!rateLimit($_POST['email'])) {
    http_response_code(429);
    exit('Too many login attempts. Try again in 5 minutes.');
}
```

---

#### 7. **Missing HSTS Preload** 🟠
**File:** `src/helpers.php` (line 34)  
**Severity:** 🟠 MEDIUM

```php
// Current:
"Strict-Transport-Security: max-age=31536000; includeSubDomains; preload"

// Already has preload ✅ – Good!
// But ensure: max-age >= 31536000 (1 year) ✅ Correct
```

---

### **MEDIUM PRIORITY FINDINGS**

#### 8. **Error Messages Expose Stack Traces** 🟡
**File:** `src/Database.php` (line 39-41)  
**Severity:** 🟡 MEDIUM

```php
// Currently good:
error_log('Database connection failed: ' . $e->getMessage());
throw new RuntimeException('Database connection unavailable.');
// ✅ Error logged, not exposed to user
```

**Verify entire codebase:**
```bash
grep -r "throw new" public/ src/ | grep "\$e->getMessage()" | grep -v "error_log"
```

---

#### 9. **Missing SQL Injection Injection Points** 🟡
**File:** All database operations  
**Severity:** 🟡 MEDIUM

**Verify all queries use prepared statements:**
```php
// ✅ GOOD:
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);

// ❌ BAD (search for):
$query = "SELECT * FROM users WHERE id = $id";
$db->query($query);
```

**Audit Command:**
```bash
grep -r "->query(" public/ src/ | grep -v "prepare"
grep -r "\$db->exec(" public/ src/
```

---

#### 10. **No Session Timeout** 🟡
**File:** `src/helpers.php` (line 15-22)  
**Severity:** 🟡 MEDIUM

```php
// Current:
session_set_cookie_params([
    'lifetime' => 0,  // Browser session only (good)
    // But no idle timeout mechanism
]);
```

**Fix:**
```php
// Add session timeout tracking:
const SESSION_TIMEOUT = 1800; // 30 minutes

function checkSessionTimeout(): void
{
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_destroy();
            header('Location: /login.php?expired=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

// Call in requireLogin():
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    checkSessionTimeout();
}
```

---

#### 11. **No File Integrity Verification** 🟡
**File:** `public/clients.php` (image upload)  
**Severity:** 🟡 MEDIUM

**Risk:** Uploaded files could be modified post-storage

**Fix:**
```php
// Store SHA-256 hash with file
$fileHash = hash_file('sha256', $destPath);
$stmt = $db->prepare(
    'INSERT INTO client_logos (filename, sha256_hash) VALUES (?, ?)'
);
$stmt->execute([$newFilename, $fileHash]);

// Verify on retrieval:
function verifyLogo(string $filename, string $storedHash): bool
{
    $path = LOGO_UPLOAD_DIR . $filename;
    return hash_file('sha256', $path) === $storedHash;
}
```

---

### **LOW PRIORITY FINDINGS**

#### 12. **Missing Security.txt** 🔵
**Severity:** 🔵 LOW (Best Practice)

**File:** `public/.well-known/security.txt`

```
Contact: security@yourdomain.com
Expires: 2025-12-31T23:59:00Z
Preferred-Languages: en
```

---

#### 13. **No CORS Headers** 🔵
**Severity:** 🔵 LOW

**Fix in `src/helpers.php`:**
```php
header("Access-Control-Allow-Origin: https://trusted-domain.com");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Credentials: true");
```

---

## 📋 Summary Table

| ID | Issue | Severity | Status | Priority |
|---|---|---|---|---|
| ASF-001 | Hardcoded credentials in docker-compose.yml | 🔴 CRITICAL | ❌ UNFIXED | P0 |
| ASF-002 | SSRF Protection | 🟢 OK | ✅ FIXED | N/A |
| ASF-004 | File Upload Validation | 🟢 OK | ✅ FIXED | N/A |
| ASF-005 | Session Cookie Flags | 🟢 OK | ✅ FIXED | N/A |
| ASF-006 | Security Headers | 🟡 PARTIAL | ⚠️ REVIEW | P1 |
| ASF-007 | Password Security | 🟢 OK | ✅ FIXED | N/A |
| ASF-009 | ZAP API Key (root compose) | 🔴 CRITICAL | ❌ UNFIXED | P0 |
| ZAP Port Exposure | Database Access | 🔴 CRITICAL | ❌ UNFIXED | P0 |
| DB Port Exposure | Port 3306 public | 🔴 CRITICAL | ❌ UNFIXED | P0 |
| Docker Tag Versioning | Floating tags | 🟠 HIGH | ⚠️ PARTIAL | P1 |
| CSP Inline Styles | XSS Risk | 🟠 HIGH | ❌ UNFIXED | P2 |
| Rate Limiting | Brute Force | 🟠 HIGH | ❌ UNFIXED | P2 |
| Session Timeout | Idle Timeout | 🟡 MEDIUM | ❌ UNFIXED | P3 |
| File Integrity | Post-upload verification | 🟡 MEDIUM | ❌ UNFIXED | P3 |

---

## 🚀 Immediate Action Items

### **Within 24 Hours (Critical)**
1. ✅ Use hardened `/docker/docker-compose.yml` instead of root version
2. 🔴 Rotate all credentials in production systems
3. 🔴 Purge credentials from git history: `git filter-repo --replace-text .env`
4. 🔴 Force push cleaned history (if development only)

### **Within 1 Week (High)**
1. Implement rate limiting on login endpoints
2. Replace floating Docker image tags with pinned versions
3. Replace unsafe-inline CSS with nonce-based CSP
4. Add session idle timeout mechanism

### **Within 2 Weeks (Medium)**
1. Add file integrity verification (SHA-256)
2. Implement comprehensive security logging
3. Add WAF/DDoS protection (Cloudflare, AWS WAF)
4. Set up security monitoring & alerting

---

## 📚 Security Headers Validation

```bash
# Test HSTS:
curl -I https://yourdomain.com | grep -i "strict-transport"

# Test CSP:
curl -I https://yourdomain.com | grep -i "content-security-policy"

# Test CORS:
curl -I https://yourdomain.com | grep -i "access-control"

# Full security header audit:
# https://securityheaders.com
# https://csp-evaluator.withgoogle.com
```

---

## 🔐 Deployment Checklist

- [ ] All credentials in `.env` (never in docker-compose.yml)
- [ ] `.env` added to `.gitignore` and never committed
- [ ] Docker images pinned to specific patch versions
- [ ] No default ports exposed except web app (8080)
- [ ] Database port (3306) NOT bound to host
- [ ] ZAP port (8090) NOT bound to host
- [ ] ZAP API key enforced from environment variable
- [ ] Rate limiting enabled on login/API endpoints
- [ ] Session timeout configured (30 min idle)
- [ ] HSTS max-age >= 31536000
- [ ] CSP without `unsafe-inline`
- [ ] File uploads verified for integrity
- [ ] Security headers validated
- [ ] WAF rules configured
- [ ] Secrets rotation schedule established

---

## 🎯 Recommendations for Production

### Network Security
```yaml
# Use Docker secrets instead of environment variables for database:
secrets:
  db_password:
    file: ./secrets/db_password.txt
    
services:
  db:
    secrets:
      - db_password
```

### Secrets Management
- Use HashiCorp Vault or AWS Secrets Manager
- Implement credential rotation every 30-90 days
- Audit all secret access

### Monitoring
- Set up ELK/Splunk for centralized logging
- Monitor failed login attempts
- Alert on security header violations
- Track file upload anomalies

---

## 📞 Support & References

- **OWASP Top 10:** https://owasp.org/www-project-top-ten/
- **PHP Security:** https://www.php.net/manual/en/security.php
- **Docker Security:** https://docs.docker.com/engine/security/
- **CSP Reference:** https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP

---

**Report Generated:** 2024  
**Next Review:** 2025 (Annual)
