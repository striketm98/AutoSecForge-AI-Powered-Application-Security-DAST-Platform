<?php
// ============================================================
// AutoSecForge – audit.php
// FIX ASF-003: IDOR – Audit Suppress Action
//
// Same ownership-JOIN pattern as review.php (see comments there).
// ============================================================

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Database.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verifyCsrfToken()) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$action    = trim($_POST['action']     ?? '');
$findingId = filter_input(INPUT_POST, 'finding_id', FILTER_VALIDATE_INT);
$userId    = (int) $_SESSION['user_id'];

$allowedActions = ['suppress', 'reopen', 'escalate'];
if (!$findingId || !in_array($action, $allowedActions, true)) {
    http_response_code(400);
    exit('Invalid request parameters.');
}

$statusMap = [
    'suppress'  => 'false_positive',
    'reopen'    => 'open',
    'escalate'  => 'escalated',
];

try {
    $db = Database::getInstance();

    // ASF-003 FIX: project ownership JOIN – same pattern as review.php
    $stmt = $db->prepare(
        'UPDATE findings f
         JOIN scan_runs sr        ON sr.id = f.scan_run_id
         JOIN projects  p         ON p.id  = sr.project_id
         JOIN project_members pm  ON pm.project_id = p.id AND pm.user_id = :uid
         SET f.status = :status, f.updated_at = NOW()
         WHERE f.id = :fid'
    );

    $stmt->execute([
        ':uid'    => $userId,
        ':status' => $statusMap[$action],
        ':fid'    => $findingId,
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        exit('Access denied or finding not found.');
    }

    header('Location: /audit.php?updated=1');
    exit;

} catch (Throwable $e) {
    error_log('audit.php error: ' . $e->getMessage());
    http_response_code(500);
    exit('Server error.');
}
