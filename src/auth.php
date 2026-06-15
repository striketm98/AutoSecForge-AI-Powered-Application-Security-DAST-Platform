<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    // App is served over HTTPS only (port 80 redirects to 443) — never let the
    // session cookie travel over plaintext HTTP.
    ini_set('session.cookie_secure', 1);
    session_start();
}
require_once 'Database.php';
function authenticateUser($email, $password) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    return false;
}
function require_auth() { if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; } }
?>
