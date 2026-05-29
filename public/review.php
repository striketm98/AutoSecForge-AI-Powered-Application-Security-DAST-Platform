<?php
// ============================================================
// AutoSecForge – review.php
// FIX ASF-003: IDOR – Finding Suppression
//
// BEFORE (vulnerable):
//   UPDATE findings SET status=?, ... WHERE id=?
//   – no ownership check; any authenticated user could suppress
//     any finding by supplying an arbitrary finding_id.
//
// AFTER (fixed):
//   UPDATE findings f
//   JOIN scan_runs sr ON sr.id = f.scan_run_id
//   JOIN projects  p  ON p.id  = sr.project_id
//   JOIN project_members pm ON pm.project_id = p.id
//   SET f.status = ?, f.updated_at = NOW()
//   WHERE f.id = ? AND pm.user_id = ?
//
//   The extra JOINs guarantee that the row is only updated when
//   the authenticated user is a member of the project that owns
//   the finding.  If the finding does not belong to the user's
//   project, zero rows are updated and a 403 is returned.
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

$findingId = filter_input(INPUT_POST, 'finding_id', FILTER_VALIDATE_INT);
$status    = trim($_POST['status'] ?? '');
$userId    = (int) $_SESSION['user_id'];

$allowedStatuses = ['open', 'false_positive', 'accepted_risk', 'resolved'];
if (!$findingId || !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    exit('Invalid request parameters.');
}

try {
    $db = Database::getInstance();

    // ASF-003 FIX: Project ownership JOIN ensures only a project
    // member can update a finding that belongs to their project.
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
        ':status' => $status,
        ':fid'    => $findingId,
    ]);

    if ($stmt->rowCount() === 0) {
        // Either the finding doesn't exist or the user doesn't own it.
        http_response_code(403);
        exit('Access denied or finding not found.');
    }

    // Return JSON for AJAX callers; redirect for form-based callers
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    header('Location: /review.php?updated=1');
    exit;

} catch (Throwable $e) {
    error_log('review.php error: ' . $e->getMessage());
    http_response_code(500);
    exit('Server error.');
}
