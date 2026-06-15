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
 * All client accounts (role='client'), with their company name if set.
 * Used to populate the "Client" picker on scan pages. Returns [] on error.
 */
function asf_clients(?PDO $pdo = null): array {
    try {
        if (!class_exists('Database')) require_once __DIR__ . '/Database.php';
        $pdo ??= Database::getInstance();
        return $pdo->query(
            "SELECT u.id, u.full_name, c.company
               FROM users u LEFT JOIN clients c ON c.user_id = u.id
              WHERE u.role = 'client' ORDER BY u.full_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) { return []; }
}

/**
 * Validate a posted client id: returns the int id only if it is a real
 * account with role='client', otherwise null (so unscoped scans store NULL).
 */
function asf_valid_client_id($id, ?PDO $pdo = null): ?int {
    $id = (int)$id;
    if ($id <= 0) return null;
    try {
        if (!class_exists('Database')) require_once __DIR__ . '/Database.php';
        $pdo ??= Database::getInstance();
        $s = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'client'");
        $s->execute([$id]);
        return $s->fetchColumn() ? $id : null;
    } catch (Throwable) { return null; }
}

/**
 * Visibility scope for scan reports, by the current session role. Returns
 * [sqlCondition, params] to AND into a scan_jobs query (table alias `j`):
 *   - client  → only their own reports (j.client_id = them)
 *   - analyst → only reports they ran  (j.triggered_by = them)
 *   - admin/manager/auditor/executive → everything (optionally a ?client filter)
 * $clientFilter is an optional staff-side client-id filter (ignored for
 * client/analyst, who are already locked to their own rows).
 */
function asf_report_scope(?int $clientFilter = null): array {
    $role = $_SESSION['user_role'] ?? '';
    $me   = (int)($_SESSION['user_id'] ?? 0);
    if ($role === 'client')  return ['j.client_id = ?',   [$me]];
    if ($role === 'analyst') return ['j.triggered_by = ?', [$me]];
    if ($clientFilter)       return ['j.client_id = ?',   [$clientFilter]];
    return ['1 = 1', []];
}

/**
 * Can the current user view/export the given report? Staff leads see all;
 * a client sees only reports scoped to them; an analyst only their own.
 */
function asf_can_view_report(PDO $pdo, int $jobId): bool {
    $role = $_SESSION['user_role'] ?? '';
    $me   = (int)($_SESSION['user_id'] ?? 0);
    if (in_array($role, ['admin','manager','auditor','executive'], true)) return true;
    $col = $role === 'client' ? 'client_id' : 'triggered_by';
    try {
        $s = $pdo->prepare("SELECT 1 FROM scan_jobs WHERE id = ? AND $col = ?");
        $s->execute([$jobId, $me]);
        return (bool)$s->fetchColumn();
    } catch (Throwable) { return false; }
}

/**
 * Recipients for a scan/report event: the triggering user plus every admin
 * and manager. When the scan is scoped to a client ($clientId), that client
 * is included too, so they're notified their report is ready.
 */
function asf_scan_recipients(?int $triggeredBy, ?int $clientId = null): array {
    $ids = $triggeredBy ? [$triggeredBy] : [];
    if ($clientId) $ids[] = $clientId;
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
