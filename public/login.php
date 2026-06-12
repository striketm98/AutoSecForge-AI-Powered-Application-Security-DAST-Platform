<?php
session_start();
require_once '../src/auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (authenticateUser($email, $password)) {
        header('Location: home.php');
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>AutoSecForge Pro | Secure Login</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 (free icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        /* Animated background blobs */
        body::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(99,102,241,0.4) 0%, rgba(99,102,241,0) 70%);
            top: -100px;
            left: -100px;
            border-radius: 50%;
            animation: float 12s infinite alternate;
        }
        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(139,92,246,0.3) 0%, rgba(139,92,246,0) 70%);
            bottom: -150px;
            right: -150px;
            border-radius: 50%;
            animation: float 15s infinite alternate-reverse;
        }
        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 30px) scale(1.2); }
        }
        .login-container {
            width: 100%;
            max-width: 460px;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .login-card {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            border-radius: 2rem;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 35px 55px -15px rgba(0, 0, 0, 0.6);
        }
        .logo-area {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 1.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 10px 20px -5px rgba(99,102,241,0.4);
        }
        .logo-icon i {
            font-size: 2.2rem;
            color: white;
        }
        h1 {
            font-size: 1.9rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, #c7d2fe);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.02em;
        }
        .subtitle {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .input-group {
            margin-bottom: 1.5rem;
        }
        .input-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #cbd5e1;
            margin-bottom: 0.5rem;
        }
        .input-field {
            position: relative;
        }
        .input-field i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1.1rem;
            pointer-events: none;
        }
        .input-field input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid #334155;
            border-radius: 1rem;
            font-size: 1rem;
            color: #f1f5f9;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .input-field input:focus {
            outline: none;
            border-color: #8b5cf6;
            background: rgba(30, 41, 59, 0.9);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }
        .input-field input::placeholder {
            color: #475569;
        }
        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.8rem;
            font-size: 0.85rem;
        }
        .checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #cbd5e1;
            cursor: pointer;
        }
        .checkbox input {
            width: 1rem;
            height: 1rem;
            accent-color: #8b5cf6;
            cursor: pointer;
        }
        .forgot-link {
            color: #a5b4fc;
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-link:hover {
            color: #c7d2fe;
            text-decoration: underline;
        }
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            padding: 0.9rem;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 4px 12px rgba(99,102,241,0.3);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
            box-shadow: 0 8px 20px rgba(99,102,241,0.4);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .social-login {
            margin-top: 2rem;
            text-align: center;
        }
        .social-login p {
            color: #94a3b8;
            font-size: 0.8rem;
            margin-bottom: 1rem;
            position: relative;
        }
        .social-login p::before,
        .social-login p::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: #334155;
        }
        .social-login p::before { left: 0; }
        .social-login p::after { right: 0; }
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: rgba(51, 65, 85, 0.7);
            border-radius: 1rem;
            color: #cbd5e1;
            font-size: 1.2rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        .social-icons a:hover {
            background: #8b5cf6;
            color: white;
            transform: translateY(-3px);
        }
        .register-link {
            text-align: center;
            margin-top: 1.8rem;
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .register-link a {
            color: #a5b4fc;
            text-decoration: none;
            font-weight: 500;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .error-message {
            background: rgba(220, 38, 38, 0.15);
            backdrop-filter: blur(4px);
            border-left: 4px solid #ef4444;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            color: #fca5a5;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .error-message i {
            font-size: 1rem;
        }
        @media (max-width: 480px) {
            .login-card { padding: 1.8rem; }
            h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="logo-area">
            <div class="logo-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"
                     width="36" height="36" aria-label="AutoSecForge">
                  <path d="M60 8 L98 24 L98 58 Q98 88 60 108 Q22 88 22 58 L22 24 Z"
                        fill="none" stroke="white" stroke-width="6"
                        stroke-linejoin="round" stroke-linecap="round"/>
                  <rect x="46" y="60" width="28" height="20" rx="4" fill="white"/>
                  <path d="M50 60 L50 52 Q50 38 60 38 Q70 38 70 52 L70 60"
                        fill="none" stroke="white" stroke-width="5"
                        stroke-linecap="round"/>
                  <circle cx="60" cy="68" r="3.5" fill="#6366f1"/>
                  <rect x="58" y="68" width="4" height="6" rx="1.5" fill="#6366f1"/>
              </svg>
            </div>
            <h1>AutoSecForge Pro</h1>
            <p class="subtitle">Enterprise Security Orchestration</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>EMAIL ADDRESS</label>
                <div class="input-field">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="admin@cyber-security.local" required autofocus>
                </div>
            </div>
            <div class="input-group">
                <label>PASSWORD</label>
                <div class="input-field">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
            </div>
            <div class="options">
                <label class="checkbox">
                    <input type="checkbox" id="remember"> <span>Remember me</span>
                </label>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-arrow-right-to-bracket" style="margin-right: 8px;"></i> Sign In
            </button>
        </form>

        <div class="social-login">
            <p>Or continue with</p>
            <div class="social-icons">
                <a href="#"><i class="fab fa-google"></i></a>
                <a href="#"><i class="fab fa-github"></i></a>
                <a href="#"><i class="fab fa-microsoft"></i></a>
                <a href="#"><i class="fab fa-facebook-f"></i></a>
            </div>
        </div>

        <div class="register-link">
            Don't have an account? <a href="#">Request Access</a>
        </div>
    </div>
</div>
</body>
</html>
