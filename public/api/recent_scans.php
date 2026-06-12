<?php
require_once '../../src/auth.php';
require_auth();
header('Content-Type: application/json');

$limit = min((int)($_GET['limit'] ?? 10), 100);

try {
    $pdo  = Database::getInstance();
    $rows = $pdo->query(
        "SELECT j.id, j.target, j.scan_types, j.status, j.model, j.created_at, u.full_name analyst
           FROM scan_jobs j LEFT JOIN users u ON u.id=j.triggered_by
          ORDER BY j.created_at DESC LIMIT $limit"
    )->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true, 'data'=>$rows]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
