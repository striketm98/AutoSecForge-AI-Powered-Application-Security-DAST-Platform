<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= $page_title ?? 'AutoSecForge Pro' ?></title>
    <!-- Bootstrap 5 + Icons + AdminLTE -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f4f6f9; }
        .navbar-brand img { height: 32px; margin-right: 8px; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .sidebar .nav-link { border-radius: 0.5rem; margin: 2px 8px; padding: 10px 16px; }
        .sidebar .nav-link.active { background: #eef2ff; color: #3b82f6; }
        .navbar { box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        @media (max-width: 768px) {
            .sidebar { position: fixed; top: 56px; left: -250px; width: 250px; height: calc(100% - 56px); transition: left 0.3s; z-index: 1050; background: white; overflow-y: auto; }
            .sidebar.show { left: 0; }
            .content-wrapper { margin-left: 0 !important; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container-fluid">
        <button class="btn btn-link d-lg-none" type="button" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <a class="navbar-brand fw-bold" href="home.php">
            <img src="/assets/images/logo.png" alt="Logo" onerror="this.style.display='none'"> AutoSecForge Pro
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-muted d-none d-md-block"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
            <div class="dropdown">
                <a class="dropdown-toggle text-decoration-none text-dark" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle fa-2x text-secondary"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<div class="d-flex">
    <div class="sidebar bg-white shadow-sm vh-100 flex-shrink-0 p-3" style="width: 260px;" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['SCRIPT_NAME'])=='home.php'?'active':'' ?>" href="home.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['SCRIPT_NAME'])=='scan_trigger.php'?'active':'' ?>" href="scan_trigger.php"><i class="fas fa-bolt me-2"></i>Trigger Scan</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['SCRIPT_NAME'])=='scan_jobs.php'?'active':'' ?>" href="scan_jobs.php"><i class="fas fa-history me-2"></i>Scan History</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['SCRIPT_NAME'])=='report.php'?'active':'' ?>" href="report.php"><i class="fas fa-file-alt me-2"></i>Reports</a></li>
            <?php if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'manager'): ?>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['SCRIPT_NAME'])=='clients.php'?'active':'' ?>" href="clients.php"><i class="fas fa-users me-2"></i>Clients</a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['SCRIPT_NAME'])=='checklist.php'?'active':'' ?>" href="checklist.php"><i class="fas fa-check-circle me-2"></i>Compliance</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['SCRIPT_NAME'])=='audit.php'?'active':'' ?>" href="audit.php"><i class="fas fa-shield-alt me-2"></i>Audit Logs</a></li>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['SCRIPT_NAME'])=='addons.php'?'active':'' ?>" href="addons.php"><i class="fas fa-puzzle-piece me-2"></i>Modules</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['SCRIPT_NAME'])=='settings.php'?'active':'' ?>" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="content-wrapper flex-grow-1 p-3 p-md-4">
