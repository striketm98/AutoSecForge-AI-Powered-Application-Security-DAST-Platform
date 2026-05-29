<?php
// ============================================================
// AutoSecForge – login.php
// FIX ASF-008: Removed hard-coded admin@cyber-security.local
//              pre-fill from the email input field.
// ============================================================

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Database.php';

// Redirect already-authenticated users
if (!empty($_SESSION['user_id'])) {
    header('Location: /home.php');
    exit;
}

$csrfToken  = generateCsrfToken();
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoSecForge – Login</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="login-page">
  <div class="login-card">
    <h1>AutoSecForge</h1>
    <p class="subtitle">AI-Powered Application Security Platform</p>

    <?php if ($flashError): ?>
      <div class="alert alert-danger"><?= e($flashError) ?></div>
    <?php endif; ?>

    <form method="POST" action="/auth.php" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

      <label for="email">Email</label>
      <!--
        ASF-008 FIX: value attribute intentionally left empty.
        Previously contained: value='admin@cyber-security.local'
        which exposed the admin account name to any site visitor.
      -->
      <input type="email" id="email" name="email"
             placeholder="you@example.com"
             required autocomplete="username">

      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             required autocomplete="current-password">

      <button type="submit">Sign in</button>
    </form>
  </div>
</body>
</html>
