<?php
// ============================================================
// AutoSecForge – auth.php
// CSRF token validation retained and confirmed.
// Session regeneration on login added for session-fixation
// prevention (hardening on top of existing CSRF check).
// ============================================================

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Database.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

// CSRF validation (T04 – confirmed pass in DAST)
if (!verifyCsrfToken()) {
    $_SESSION['flash_error'] = 'Session expired. Please try again.';
    header('Location: /login.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

if ($email === '' || $password === '') {
    $_SESSION['flash_error'] = 'Email and password are required.';
    header('Location: /login.php');
    exit;
}

// loginAttempt() handles Argon2ID verification + transparent
// SHA-256 → Argon2ID migration (ASF-007 FIX in helpers.php).
if (loginAttempt($email, $password)) {
    // session_regenerate_id(true) is already called inside loginAttempt()
    header('Location: /home.php');
    exit;
}

$_SESSION['flash_error'] = 'Invalid email or password.';
header('Location: /login.php');
exit;
