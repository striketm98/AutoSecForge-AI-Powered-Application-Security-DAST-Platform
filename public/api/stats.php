<?php
require_once '../../src/auth.php';
require_auth();
header('Content-Type: application/json');

try {
    $pdo = Database::getInstance();

    $totals = $pdo->query(
        "SELECT COUNT(*) total,
                SUM(status='completed') completed,
                SUM(status='partial') partial,
                SUM(status='failed') failed
           FROM scan_jobs"
    )->fetch(PDO::FETCH_ASSOC);

    $week = $pdo->query(
        "SELECT DATE(created_at) d, COUNT(*) n
           FROM scan_jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          GROUP BY d ORDER BY d"
    )->fetchAll(PDO::FETCH_ASSOC);

    $last = $pdo->query(
        "SELECT MAX(created_at) FROM scan_jobs"
    )->fetchColumn();

    echo json_encode([
        'ok'          => true,
        'total_scans' => (int)($totals['total']     ?? 0),
        'completed'   => (int)($totals['completed'] ?? 0),
        'partial'     => (int)($totals['partial']   ?? 0),
        'failed'      => (int)($totals['failed']    ?? 0),
        'week'        => $week,
        'last_scan'   => $last,
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
