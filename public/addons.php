<?php
// ============================================================
// AutoSecForge – addons.php
// FIX ASF-002: SSRF via endpoint_url
//
// All outbound HTTP calls now go through safeFetch() (defined in
// helpers.php) which enforces the SSRF allowlist before making
// any network request.  Direct file_get_contents() on user-supplied
// URLs has been removed entirely.
// ============================================================

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Database.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    include __DIR__ . '/../templates/addons_form.php';
    exit;
}

if (!verifyCsrfToken()) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$action      = trim($_POST['action']       ?? '');
$endpointUrl = trim($_POST['endpoint_url'] ?? '');

// ---- Health-check action ----
if ($action === 'health_check') {
    if ($endpointUrl === '') {
        $_SESSION['flash_error'] = 'Endpoint URL is required.';
        header('Location: /addons.php');
        exit;
    }

    // ASF-002 FIX: toolHealth() wraps safeFetch(), which blocks
    // loopback / RFC-1918 / link-local addresses before fetching.
    $result = toolHealth($endpointUrl);

    if ($result['status'] === 'error') {
        $_SESSION['flash_error'] = 'Endpoint URL not permitted: ' . e($result['detail']);
    } elseif ($result['status'] === 'unreachable') {
        $_SESSION['flash_warning'] = 'Endpoint unreachable: ' . e($result['detail']);
    } else {
        $_SESSION['flash_success'] = 'Endpoint is healthy.';
    }

    header('Location: /addons.php');
    exit;
}

// ---- Save addon configuration ----
if ($action === 'save') {
    $addonName  = trim($_POST['addon_name']  ?? '');
    $apiBaseUrl = trim($_POST['api_base_url'] ?? '');

    if ($addonName === '' || $apiBaseUrl === '') {
        $_SESSION['flash_error'] = 'Addon name and API base URL are required.';
        header('Location: /addons.php');
        exit;
    }

    // Validate the URL is permitted before storing it
    try {
        assertSafeUrl($apiBaseUrl);
    } catch (InvalidArgumentException $e) {
        error_log('SSRF blocked on addon save: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'API base URL is not permitted.';
        header('Location: /addons.php');
        exit;
    }

    try {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO addons (name, api_base_url, created_by, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE api_base_url = VALUES(api_base_url)'
        );
        $stmt->execute([$addonName, $apiBaseUrl, $_SESSION['user_id']]);
        $_SESSION['flash_success'] = 'Addon saved.';
    } catch (Throwable $e) {
        error_log('addons.php DB error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Database error – please try again.';
    }

    header('Location: /addons.php');
    exit;
}

http_response_code(400);
exit('Unknown action.');
