<?php
require_once __DIR__ . '/../src/auth.php';
require_auth();

$format = $_GET['format'] ?? 'json';
$project_id = (int)($_GET['project_id'] ?? 0);
if (!$project_id || !user_can_access_project($project_id)) {
    http_response_code(403);
    exit('Access denied.');
}

// Fetch data (simplified – in real life you would join project tables)
$data = [
    'project' => ['name' => 'Demo Project', 'client' => 'Acme Corp'],
    'findings' => [
        ['title' => 'SQL Injection', 'severity' => 'High', 'cwe' => 'CWE-89'],
        ['title' => 'XSS', 'severity' => 'Medium', 'cwe' => 'CWE-79'],
    ],
    'generated_at' => date('Y-m-d H:i:s'),
];

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// HTML fallback for PDF/Word/Excel (user can save as)
header('Content-Type: text/html; charset=utf-8');
echo '<html><head><title>Security Report</title></head><body>';
echo '<h1>Security Assessment Report</h1>';
echo '<p>Generated: ' . $data['generated_at'] . '</p>';
echo '<table border="1"><tr><th>Title</th><th>Severity</th><th>CWE</th></tr>';
foreach ($data['findings'] as $f) {
    echo "<tr><td>{$f['title']}</td><td>{$f['severity']}</td><td>{$f['cwe']}</td></tr>";
}
echo '</table></body></html>';
