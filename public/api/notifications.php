<?php
// Notifications API — list recent notifications for the current user and
// mark them read. GET returns {count, items}; POST {action:'read'} marks all
// (or a single id) read.
require_once '../../src/auth.php';
require_once '../../src/helpers.php';
require_auth();
header('Content-Type: application/json');

$uid = (int)($_SESSION['user_id'] ?? 0);
if (!$uid) { http_response_code(401); echo json_encode(['error' => 'unauthenticated']); exit; }

try {
    $pdo = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        if (($in['action'] ?? '') === 'read') {
            if (!empty($in['id']) && ctype_digit((string)$in['id'])) {
                $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
                    ->execute([(int)$in['id'], $uid]);
            } else {
                $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
                    ->execute([$uid]);
            }
            echo json_encode(['ok' => true]);
            exit;
        }
        http_response_code(400); echo json_encode(['error' => 'unknown action']); exit;
    }

    // GET — unread count + most recent 15
    $cstmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $cstmt->execute([$uid]);
    $count = (int)$cstmt->fetchColumn();

    $lstmt = $pdo->prepare(
        'SELECT id, type, title, body, link, is_read, created_at
           FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15'
    );
    $lstmt->execute([$uid]);
    $items = $lstmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['count' => $count, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db', 'detail' => $e->getMessage()]);
}
