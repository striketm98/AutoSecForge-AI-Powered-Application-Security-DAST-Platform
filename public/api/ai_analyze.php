<?php
require_once '../../src/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'POST required']); exit;
}

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$text    = trim($input['text'] ?? '');
$context = trim($input['context'] ?? 'general security analysis');

if (!$text) { echo json_encode(['error'=>'text required']); exit; }
if (strlen($text) > 50000) { echo json_encode(['error'=>'Input too large (max 50000 chars)']); exit; }

$env      = @parse_ini_file('/var/www/html/.env', false, INI_SCANNER_RAW) ?: [];
$ai_url   = rtrim($env['AI_AGENT_URL'] ?? 'http://openai-free-agents:6400', '/');

$payload = json_encode([
    'messages' => [
        ['role'=>'user', 'content'=>"Context: $context\n\n---\n$text"]
    ]
]);
$ch = curl_init("$ai_url/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>120,
]);
$raw = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) { echo json_encode(['error'=>"AI Agent: $err"]); exit; }
$res = json_decode($raw, true);
echo json_encode([
    'ok'       => true,
    'analysis' => $res['choices'][0]['message']['content'] ?? 'No response',
    'model'    => $res['model'] ?? 'unknown',
]);
