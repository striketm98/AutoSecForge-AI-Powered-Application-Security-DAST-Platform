<?php
require_once __DIR__ . '/../src/auth.php';
require_auth();

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        if (login($_SESSION['user_email'], $current)) {
            $db = db();
            $hash = hash_password($new);
            $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
            $stmt->execute([$hash, $_SESSION['user_email']]);
            $message = "Password changed successfully!";
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Change Password - AutoSecForge</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;margin:0;padding:20px}
    .container{max-width:500px;margin:2rem auto;background:rgba(30,41,59,0.7);border-radius:1rem;padding:2rem;border:1px solid rgba(255,255,255,0.1)}
    h1{color:#667eea;margin-bottom:1.5rem}
    .form-group{margin-bottom:1.25rem}
    label{display:block;margin-bottom:0.5rem;color:#94a3b8}
    input{width:100%;padding:0.75rem;background:rgba(15,23,42,0.8);border:1px solid #334155;border-radius:0.5rem;color:#f1f5f9}
    button{background:linear-gradient(135deg,#667eea,#764ba2);border:none;padding:0.75rem 1.5rem;border-radius:0.5rem;color:white;cursor:pointer}
    .success{background:rgba(34,197,94,0.2);border-left:4px solid #22c55e;padding:0.75rem;margin-bottom:1rem}
    .error{background:rgba(220,38,38,0.2);border-left:4px solid #ef4444;padding:0.75rem;margin-bottom:1rem;color:#fca5a5}
    .btn-secondary{background:#334155;margin-left:0.5rem}
</style>
</head>
<body>
<div class="container">
    <h1>Change Password</h1>
    <?php if($message): ?><div class="success"><?php echo $message; ?></div><?php endif; ?>
    <?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit">Update Password</button>
        <a href="dashboard.php" class="btn-secondary" style="display:inline-block;padding:0.75rem 1.5rem;text-decoration:none;border-radius:0.5rem;margin-left:0.5rem">Cancel</a>
    </form>
</div>
</body>
</html>
