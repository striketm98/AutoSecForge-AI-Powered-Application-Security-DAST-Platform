<?php
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';

session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (login($email, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoSecForge Pro – Login</title>
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
    <!-- Local CSS (no CDNs) -->
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(15,23,42,0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 1.5rem;
            padding: 2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-title {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg,#667eea,#764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .input-group {
            margin-bottom: 1.25rem;
        }
        .input-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .input-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(30,41,59,0.8);
            border: 1px solid #334155;
            border-radius: 0.75rem;
            color: #f1f5f9;
            transition: all 0.2s;
        }
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.2);
        }
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg,#667eea,#764ba2);
            border: none;
            padding: 0.75rem;
            border-radius: 0.75rem;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: transform 0.1s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
        }
        .error-message {
            background: rgba(220,38,38,0.2);
            border-left: 4px solid #ef4444;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            color: #fca5a5;
        }
        .text-center { text-align: center; }
        .text-muted { color: #94a3b8; }
        .mt-2 { margin-top: 0.5rem; }
        .mt-6 { margin-top: 1.5rem; }
        .logo-img { max-width: 180px; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center">
        <img src="/assets/img/logo.svg" alt="AutoSecForge Pro" class="logo-img">
        <p class="text-muted mt-2">Enterprise Application Security Platform</p>
    </div>
    <?php if ($error): ?>
        <div class="error-message">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="input-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="admin@cyber-security.local" required autofocus>
        </div>
        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-login">Sign In →</button>
    </form>
    <div class="text-center text-muted mt-6">
        © <?php echo date('Y'); ?> AutoSecForge Pro
    </div>
</div>
<script src="/assets/js/popper.min.js"></script>
<script src="/assets/js/bootstrap.min.js"></script>
</body>
</html>
