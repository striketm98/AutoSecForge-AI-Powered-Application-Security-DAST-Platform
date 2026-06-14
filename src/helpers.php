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

/**
 * Create an in-app notification for one or more users. Best-effort: never
 * throws, so a notification failure can't break the calling action.
 *
 *   asf_notify([1,2], 'Scan report ready', 'example.com — network', 'report.php');
 */
function asf_notify(array $userIds, string $title, string $body = '', string $link = '', string $type = 'info'): void {
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (!$userIds) return;
    try {
        if (!class_exists('Database')) require_once __DIR__ . '/Database.php';
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($userIds as $uid) {
            $stmt->execute([
                $uid, substr($type, 0, 40), substr($title, 0, 255),
                substr($body, 0, 500), substr($link, 0, 255),
            ]);
        }
    } catch (Throwable) { /* swallow — notifications must never break the request */ }
}

/**
 * Recipients for a scan/report event: the triggering user plus every
 * admin and manager. Returns a deduped list of user ids.
 */
function asf_scan_recipients(?int $triggeredBy): array {
    $ids = $triggeredBy ? [$triggeredBy] : [];
    try {
        if (!class_exists('Database')) require_once __DIR__ . '/Database.php';
        $pdo = Database::getInstance();
        $rows = $pdo->query("SELECT id FROM users WHERE role IN ('admin','manager')")
                    ->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_merge($ids, $rows);
    } catch (Throwable) { /* fall back to just the triggerer */ }
    return $ids;
}
?>
