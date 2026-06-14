<?php
require_once '../../src/auth.php';
require_once '../../src/helpers.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$target     = trim($input['target'] ?? '');
$scan_types = array_values(array_filter((array)($input['scan_types'] ?? ['network'])));

if (!$target) { echo json_encode(['error'=>'target required']); exit; }

$host = parse_url(strpos($target,'http')===0 ? $target : "http://$target", PHP_URL_HOST) ?: $target;
foreach (['127.','10.','192.168.','172.16.','172.17.','172.18.','169.254.','localhost','::1'] as $p) {
    if (str_starts_with($host,$p)) { echo json_encode(['error'=>'Private targets blocked']); exit; }
}

$env     = @parse_ini_file('/var/www/html/.env', false, INI_SCANNER_RAW) ?: [];
$mcp_url = rtrim($env['MCP_URL'] ?? 'http://mcp-hackstrike:6300', '/');

$payload = json_encode(['target'=>$target, 'scan_types'=>$scan_types]);
$ch = curl_init("$mcp_url/scan/security-review");
curl_setopt_array($ch, [
    CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>300,
]);
$raw = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) { echo json_encode(['error'=>"MCP: $err"]); exit; }
$result = json_decode($raw, true) ?? ['error'=>'Bad MCP response'];

if (empty($result['error'])) {
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO scan_jobs (target, scan_types, raw_output, analysis, model, triggered_by, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $target, implode(',', $scan_types),
            $result['raw_output'] ?? '', $result['analysis'] ?? '',
            $result['model'] ?? '', $_SESSION['user_id'] ?? null,
            $result['ok'] ? 'completed' : 'partial',
        ]);
        $job_id = $pdo->lastInsertId();
        $result['job_id'] = $job_id;

        // Notify the triggering user + all admins/managers that the report is ready.
        $triggeredBy = $_SESSION['user_id'] ?? null;
        asf_notify(
            asf_scan_recipients($triggeredBy ? (int)$triggeredBy : null),
            'Scan report ready',
            $target . ' — ' . implode(', ', $scan_types),
            'report.php?export=' . $job_id . '&format=html',
            $result['ok'] ? 'success' : 'warning'
        );
    } catch (Throwable $e) {
        $result['db_warning'] = $e->getMessage();
    }
}

echo json_encode($result);
