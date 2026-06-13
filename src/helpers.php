<?php
function is_safe_url($url) {
    $blocked = ['127.0.0.1', 'localhost', '169.254.169.254', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    foreach ($blocked as $b) if (strpos($host, $b) !== false) return false;
    return true;
}

/**
 * Write an entry to the audit_log table. Best-effort: never throws, so a
 * logging failure can't break the calling action.
 *
 *   asf_audit('scan.trigger', "target=$target types=$types");
 */
function asf_audit(string $action, string $detail = '', ?int $userId = null): void {
    try {
        if (!class_exists('Database')) require_once __DIR__ . '/Database.php';
        $pdo = Database::getInstance();
        $uid = $userId ?? ($_SESSION['user_id'] ?? null);
        $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip) $ip = substr(trim(explode(',', $ip)[0]), 0, 45);
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, action, detail, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$uid, substr($action, 0, 200), substr($detail, 0, 2000), $ip]);
    } catch (Throwable) { /* swallow — auditing must never break the request */ }
}
?>
