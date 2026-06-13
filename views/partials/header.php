<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?= htmlspecialchars($page_title ?? 'AutoSecForge Pro') ?> | AutoSecForge Pro</title>

  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Bootstrap 4 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <!-- AdminLTE 3.2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">

  <style>
    /* ── Brand tokens ───────────────────────────────── */
    :root {
      --asf-indigo:  #6366f1;
      --asf-violet:  #8b5cf6;
      --asf-navy:    #0f172a;
      --asf-slate:   #1e293b;
      --asf-surface: #f8fafc;
    }

    body { font-family: 'Inter', sans-serif; background: var(--asf-surface); }

    /* ── Top navbar ─────────────────────────────────── */
    .main-header.navbar {
      background: #ffffff;
      border-bottom: 1px solid #e2e8f0;
      box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .main-header .navbar-nav .nav-link { color: #475569 !important; }
    .brand-link {
      background: linear-gradient(135deg, var(--asf-indigo), var(--asf-violet));
      border-bottom: 1px solid rgba(255,255,255,.1) !important;
    }
    .brand-link:hover { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
    .brand-text { font-weight: 700; font-size: .95rem; letter-spacing: -.01em; color: #fff !important; }

    /* ── Sidebar ────────────────────────────────────── */
    .main-sidebar { background: var(--asf-navy) !important; }
    .sidebar { background: var(--asf-navy); }

    /* User panel */
    .user-panel { border-bottom: 1px solid rgba(255,255,255,.07) !important; }
    .user-panel .info a { color: #c7d2fe !important; font-weight: 500; }
    .user-panel .info span { color: #64748b; font-size: .75rem; }

    /* Nav items */
    .nav-sidebar > .nav-item > .nav-link {
      color: #94a3b8 !important;
      border-radius: .5rem;
      margin: 1px 8px;
      padding: 9px 14px;
      font-size: .85rem;
      font-weight: 500;
      transition: all .18s;
    }
    .nav-sidebar > .nav-item > .nav-link:hover {
      background: rgba(99,102,241,.15) !important;
      color: #c7d2fe !important;
    }
    .nav-sidebar > .nav-item > .nav-link.active {
      background: linear-gradient(90deg, var(--asf-indigo), var(--asf-violet)) !important;
      color: #fff !important;
      box-shadow: 0 2px 8px rgba(99,102,241,.4);
    }
    .nav-sidebar .nav-icon { width: 1.1rem; text-align: center; margin-right: .5rem; font-size: .9rem; }

    /* Section headers */
    .nav-header {
      color: #475569 !important;
      font-size: .65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      padding: 1rem 1.2rem .4rem !important;
    }

    /* Treeview sub-nav */
    .nav-treeview > .nav-item > .nav-link {
      color: #64748b !important;
      padding: 6px 14px 6px 2.4rem;
      font-size: .8rem;
    }
    .nav-treeview > .nav-item > .nav-link.active,
    .nav-treeview > .nav-item > .nav-link:hover { color: #c7d2fe !important; }

    /* ── Content wrapper ────────────────────────────── */
    .content-wrapper { background: var(--asf-surface) !important; }
    .content-header { padding: 1.25rem 1.5rem .5rem; }
    .content-header h1 { font-size: 1.15rem; font-weight: 700; color: #1e293b; }
    .breadcrumb { background: transparent; padding: 0; margin: 0; font-size: .78rem; }
    .breadcrumb-item a { color: var(--asf-indigo); text-decoration: none; }
    .breadcrumb-item.active { color: #64748b; }

    /* ── Cards ──────────────────────────────────────── */
    .card {
      border: none !important;
      border-radius: 1rem !important;
      box-shadow: 0 1px 4px rgba(0,0,0,.07), 0 4px 16px rgba(0,0,0,.04) !important;
      transition: box-shadow .2s, transform .2s;
    }
    .card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12) !important; }
    .card-header {
      background: transparent !important;
      border-bottom: 1px solid #f1f5f9 !important;
      border-radius: 1rem 1rem 0 0 !important;
      padding: 1rem 1.25rem .75rem;
    }
    .card-title { font-weight: 700 !important; font-size: .9rem !important; color: #1e293b; margin: 0; }
    .card-body { padding: 1.25rem; }

    /* KPI stat cards */
    .stat-card { border-radius: 1rem !important; overflow: hidden; position: relative; }
    .stat-card .stat-icon {
      width: 52px; height: 52px;
      border-radius: .875rem;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem; color: #fff;
      flex-shrink: 0;
    }
    .stat-card .stat-value { font-size: 1.9rem; font-weight: 800; line-height: 1; color: #1e293b; }
    .stat-card .stat-label { font-size: .75rem; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; }
    .stat-card .stat-trend { font-size: .72rem; font-weight: 600; }

    /* Gradient card headers */
    .card-header-gradient {
      background: linear-gradient(135deg, var(--asf-indigo), var(--asf-violet)) !important;
      color: #fff !important;
      border-bottom: none !important;
    }
    .card-header-gradient .card-title { color: #fff !important; }

    /* ── Badges & pills ─────────────────────────────── */
    .badge-critical { background: #fee2e2; color: #dc2626; }
    .badge-high     { background: #fef3c7; color: #d97706; }
    .badge-medium   { background: #dbeafe; color: #2563eb; }
    .badge-low      { background: #dcfce7; color: #16a34a; }
    .badge-info-s   { background: #f0fdf4; color: #15803d; }

    /* ── Buttons ────────────────────────────────────── */
    .btn-asf {
      background: linear-gradient(135deg, var(--asf-indigo), var(--asf-violet));
      color: #fff; border: none; font-weight: 600;
      border-radius: .625rem;
      box-shadow: 0 2px 8px rgba(99,102,241,.35);
      transition: all .2s;
    }
    .btn-asf:hover { filter: brightness(1.08); color: #fff; box-shadow: 0 4px 14px rgba(99,102,241,.5); transform: translateY(-1px); }

    /* ── Tables ─────────────────────────────────────── */
    .table thead th {
      background: #f8fafc; color: #64748b;
      font-size: .72rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .06em;
      border-top: none; border-bottom: 1px solid #e2e8f0;
    }
    .table td { font-size: .83rem; color: #374151; vertical-align: middle; border-color: #f1f5f9; }
    .table-hover tbody tr:hover { background: #f8fafc; }

    /* ── Footer ─────────────────────────────────────── */
    .main-footer {
      background: #fff;
      border-top: 1px solid #e2e8f0 !important;
      color: #94a3b8;
      font-size: .78rem;
      padding: .75rem 1.5rem;
    }

    /* ── Sidebar scrollbar ──────────────────────────── */
    .sidebar { overflow-y: auto; scrollbar-width: thin; scrollbar-color: #334155 transparent; }
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }

    /* ── Alert tweaks ───────────────────────────────── */
    .alert { border: none; border-radius: .75rem; font-size: .85rem; }
    .alert-info    { background: #eff6ff; color: #1d4ed8; }
    .alert-success { background: #f0fdf4; color: #166534; }
    .alert-warning { background: #fffbeb; color: #92400e; }
    .alert-danger  { background: #fef2f2; color: #991b1b; }

    /* ── Spinner overlay ────────────────────────────── */
    #pageSpinner {
      position: fixed; inset: 0; background: rgba(255,255,255,.7);
      display: none; z-index: 9999; align-items: center; justify-content: center;
    }

    /* ── Responsive tweaks ──────────────────────────── */
    @media (max-width: 768px) {
      .stat-card .stat-value { font-size: 1.4rem; }
      .content-header { padding: .75rem 1rem .25rem; }
    }
  </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Spinner overlay -->
  <div id="pageSpinner" style="display:none;flex">
    <div class="text-center">
      <div class="spinner-border text-indigo" style="color:var(--asf-indigo);width:3rem;height:3rem;" role="status"></div>
      <div class="mt-2 small text-muted">Processing…</div>
    </div>
  </div>

  <!-- ╔══════════════════════════════════════════════════╗
       ║  TOP NAVBAR                                      ║
       ╚══════════════════════════════════════════════════╝ -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left: sidebar toggle + breadcrumb -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button">
          <i class="fas fa-bars" style="color:#475569;"></i>
        </a>
      </li>
      <li class="nav-item d-none d-md-flex align-items-center ml-2">
        <ol class="breadcrumb mb-0 bg-transparent p-0">
          <li class="breadcrumb-item"><a href="home.php">Home</a></li>
          <li class="breadcrumb-item active"><?= htmlspecialchars($page_title ?? '') ?></li>
        </ol>
      </li>
    </ul>

    <!-- Right: notifications + user -->
    <ul class="navbar-nav ml-auto">
      <!-- Notifications -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
          <span class="badge badge-warning navbar-badge" id="notifBadge" style="display:none;">!</span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="border-radius:.75rem;border:none;box-shadow:0 8px 24px rgba(0,0,0,.12);">
          <span class="dropdown-header" style="font-weight:700;font-size:.78rem;color:#64748b;">NOTIFICATIONS</span>
          <div class="dropdown-divider"></div>
          <div id="notifList" class="px-3 py-2 text-muted small">No new notifications</div>
        </div>
      </li>

      <!-- User menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <div class="d-flex align-items-center gap-2">
            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));display:flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:700;">
              <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </div>
            <span class="d-none d-md-inline" style="font-size:.83rem;font-weight:600;color:#1e293b;">
              <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
            </span>
            <i class="fas fa-chevron-down" style="font-size:.65rem;color:#94a3b8;"></i>
          </div>
        </a>
        <div class="dropdown-menu dropdown-menu-right" style="border-radius:.75rem;border:none;box-shadow:0 8px 24px rgba(0,0,0,.12);min-width:200px;">
          <div class="px-3 py-2 border-bottom">
            <div style="font-weight:700;font-size:.85rem;color:#1e293b;"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
            <div style="font-size:.72rem;color:#64748b;"><?= ucfirst($_SESSION['user_role'] ?? 'analyst') ?></div>
          </div>
          <a href="change_password.php" class="dropdown-item py-2"><i class="fas fa-key fa-fw mr-2 text-muted"></i>Change Password</a>
          <a href="settings.php" class="dropdown-item py-2"><i class="fas fa-cog fa-fw mr-2 text-muted"></i>Settings</a>
          <div class="dropdown-divider"></div>
          <a href="logout.php" class="dropdown-item py-2 text-danger"><i class="fas fa-sign-out-alt fa-fw mr-2"></i>Sign Out</a>
        </div>
      </li>
    </ul>
  </nav>

  <!-- ╔══════════════════════════════════════════════════╗
       ║  SIDEBAR                                         ║
       ╚══════════════════════════════════════════════════╝ -->
  <aside class="main-sidebar elevation-2" style="background:var(--asf-navy);">

    <!-- Brand -->
    <a href="home.php" class="brand-link" style="background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));padding:14px 16px;display:flex;align-items:center;gap:10px;text-decoration:none;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="30" height="30" style="flex-shrink:0;">
        <path d="M60 12 L94 26 L94 58 Q94 84 60 100 Q26 84 26 58 L26 26 Z"
              fill="none" stroke="white" stroke-width="5" stroke-linejoin="round" stroke-linecap="round"/>
        <rect x="48" y="58" width="24" height="18" rx="4" fill="white"/>
        <path d="M52 58 L52 50 Q52 42 60 42 Q68 42 68 50 L68 58" fill="none" stroke="white" stroke-width="4" stroke-linecap="round"/>
        <circle cx="60" cy="66" r="3" fill="var(--asf-indigo, #6366f1)"/>
        <rect x="58.5" y="66" width="3" height="5" rx="1.5" fill="var(--asf-indigo, #6366f1)"/>
      </svg>
      <span class="brand-text font-weight-bold" style="color:#fff;font-size:.95rem;letter-spacing:-.01em;">AutoSecForge <span style="color:#c7d2fe;font-weight:400;font-size:.75rem;">Pro</span></span>
    </a>

    <div class="sidebar">
      <!-- User panel -->
      <div class="user-panel mt-3 pb-3 mb-2 d-flex" style="padding:0 1rem;">
        <div class="image">
          <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem;">
            <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
          </div>
        </div>
        <div class="info ml-2">
          <a href="change_password.php" style="color:#c7d2fe;font-weight:600;font-size:.82rem;text-decoration:none;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></a>
          <div style="color:#475569;font-size:.7rem;"><?= ucfirst($_SESSION['user_role'] ?? 'analyst') ?></div>
        </div>
      </div>

      <!-- Nav -->
      <nav class="mt-1">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

          <?php
          $cur = basename($_SERVER['SCRIPT_NAME']);
          $active = fn($p) => in_array($cur, (array)$p) ? ' active' : '';
          ?>

          <li class="nav-header">Main</li>

          <li class="nav-item">
            <a href="home.php" class="nav-link<?= $active('home.php') ?>">
              <i class="nav-icon fas fa-chart-line"></i>
              <p>Dashboard</p>
            </a>
          </li>

          <li class="nav-header">Scanning</li>

          <li class="nav-item">
            <a href="scan_trigger.php" class="nav-link<?= $active('scan_trigger.php') ?>">
              <i class="nav-icon fas fa-bolt"></i>
              <p>New Security Review</p>
              <span class="badge badge-pill ml-auto" style="background:var(--asf-indigo);color:#fff;font-size:.65rem;">AI</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="scan_jobs.php" class="nav-link<?= $active('scan_jobs.php') ?>">
              <i class="nav-icon fas fa-history"></i>
              <p>Scan History</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="mobsf.php" class="nav-link<?= $active('mobsf.php') ?>">
              <i class="nav-icon fas fa-mobile-alt"></i>
              <p>Mobile Scan</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="sast.php" class="nav-link<?= $active('sast.php') ?>">
              <i class="nav-icon fas fa-code"></i>
              <p>Code Analysis</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="oasm.php" class="nav-link<?= $active('oasm.php') ?>">
              <i class="nav-icon fas fa-crosshairs"></i>
              <p>Attack Surface</p>
            </a>
          </li>

          <li class="nav-header">Reporting</li>

          <li class="nav-item">
            <a href="report.php" class="nav-link<?= $active('report.php') ?>">
              <i class="nav-icon fas fa-file-alt"></i>
              <p>Reports</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="deliverables.php" class="nav-link<?= $active('deliverables.php') ?>">
              <i class="nav-icon fas fa-box-open"></i>
              <p>Deliverables</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="review.php" class="nav-link<?= $active('review.php') ?>">
              <i class="nav-icon fas fa-search-plus"></i>
              <p>Findings Review</p>
            </a>
          </li>

          <?php if (in_array($_SESSION['user_role'] ?? '', ['admin','manager'])): ?>
          <li class="nav-header">Management</li>
          <li class="nav-item">
            <a href="clients.php" class="nav-link<?= $active('clients.php') ?>">
              <i class="nav-icon fas fa-users"></i>
              <p>Clients</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="checklist.php" class="nav-link<?= $active('checklist.php') ?>">
              <i class="nav-icon fas fa-clipboard-check"></i>
              <p>Compliance</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="audit.php" class="nav-link<?= $active('audit.php') ?>">
              <i class="nav-icon fas fa-shield-alt"></i>
              <p>Audit Logs</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
          <li class="nav-header">System</li>
          <li class="nav-item">
            <a href="addons.php" class="nav-link<?= $active('addons.php') ?>">
              <i class="nav-icon fas fa-puzzle-piece"></i>
              <p>Modules</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="settings.php" class="nav-link<?= $active('settings.php') ?>">
              <i class="nav-icon fas fa-cog"></i>
              <p>Settings</p>
            </a>
          </li>
          <?php endif; ?>

          <li class="nav-item mt-2">
            <a href="logout.php" class="nav-link" style="color:#ef4444 !important;">
              <i class="nav-icon fas fa-sign-out-alt" style="color:#ef4444;"></i>
              <p>Sign Out</p>
            </a>
          </li>

        </ul>
      </nav>
    </div>
  </aside>

  <!-- ╔══════════════════════════════════════════════════╗
       ║  CONTENT WRAPPER                                 ║
       ╚══════════════════════════════════════════════════╝ -->
  <div class="content-wrapper">
    <!-- Page header -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row align-items-center">
          <div class="col">
            <h1 class="mb-0"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
          </div>
          <div class="col-auto" id="pageActions"></div>
        </div>
      </div>
    </div>

    <!-- Page content -->
    <div class="content">
      <div class="container-fluid">
