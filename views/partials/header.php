<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title ?? 'AutoSecForge Pro') ?> | AutoSecForge Pro</title>

  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Font Awesome 6 (kept so existing fas/far/fab icons across all pages keep working) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- AdminLTE 4 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">

  <style>
    /* ── Brand tokens ───────────────────────────────── */
    :root {
      --asf-indigo:  #6366f1;
      --asf-violet:  #8b5cf6;
      --asf-navy:    #0f172a;
      --asf-slate:   #1e293b;
      --asf-surface: #f8fafc;
    }

    body, .app-wrapper { font-family: 'Inter', sans-serif; background: var(--asf-surface); }

    /* ════════════════════════════════════════════════════════════════
       Bootstrap 4 → 5 COMPATIBILITY SHIM
       The content pages were authored against Bootstrap 4 utility names.
       Bootstrap 5 renamed the directional spacing/text/float helpers and
       dropped a few component classes. Rather than rewrite every page,
       we re-implement the old names here so existing markup keeps working.
       ════════════════════════════════════════════════════════════════ */
    .mr-0{margin-right:0!important}.mr-1{margin-right:.25rem!important}.mr-2{margin-right:.5rem!important}.mr-3{margin-right:1rem!important}.mr-4{margin-right:1.5rem!important}.mr-5{margin-right:3rem!important}
    .ml-0{margin-left:0!important}.ml-1{margin-left:.25rem!important}.ml-2{margin-left:.5rem!important}.ml-3{margin-left:1rem!important}.ml-4{margin-left:1.5rem!important}.ml-5{margin-left:3rem!important}.ml-auto{margin-left:auto!important}.mr-auto{margin-right:auto!important}
    .pl-0{padding-left:0!important}.pl-1{padding-left:.25rem!important}.pl-2{padding-left:.5rem!important}.pl-3{padding-left:1rem!important}.pl-4{padding-left:1.5rem!important}.pl-5{padding-left:3rem!important}
    .pr-0{padding-right:0!important}.pr-1{padding-right:.25rem!important}.pr-2{padding-right:.5rem!important}.pr-3{padding-right:1rem!important}.pr-4{padding-right:1.5rem!important}.pr-5{padding-right:3rem!important}
    .text-left{text-align:left!important}.text-right{text-align:right!important}
    .float-left{float:left!important}.float-right{float:right!important}
    .font-weight-bold{font-weight:700!important}.font-weight-bolder{font-weight:800!important}.font-weight-normal{font-weight:400!important}.font-weight-light{font-weight:300!important}
    .font-italic{font-style:italic!important}
    .btn-block{display:block;width:100%}
    .form-group{margin-bottom:1rem}
    .form-row{display:flex;flex-wrap:wrap;margin-right:-5px;margin-left:-5px}
    .input-group-prepend,.input-group-append{display:flex;align-items:stretch}
    .no-gutters{margin-right:0;margin-left:0}
    .dropdown-menu-right{--bs-position:end;right:0;left:auto}
    .close{float:right;font-size:1.5rem;font-weight:700;line-height:1;color:#000;opacity:.5;background:transparent;border:0;cursor:pointer}
    .close:hover{opacity:.85}
    .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
    /* BS4 contextual badge colours (BS5 dropped .badge-*) */
    .badge-pill{border-radius:10rem!important}
    .badge-secondary{background:#64748b;color:#fff}
    .badge-light{background:#f1f5f9;color:#475569}
    .badge-dark{background:#1e293b;color:#fff}
    .badge-primary{background:var(--asf-indigo);color:#fff}
    .badge-success{background:#16a34a;color:#fff}
    .badge-warning{background:#f59e0b;color:#fff}
    .badge-danger{background:#dc2626;color:#fff}
    .badge-info{background:#0891b2;color:#fff}
    /* BS5 makes .badge inline padding/colour neutral when no bg given */
    .badge{display:inline-block;padding:.35em .6em;font-size:.72em;font-weight:600;line-height:1;border-radius:.375rem}

    /* ── Top navbar ─────────────────────────────────── */
    .app-header.navbar {
      background: #ffffff !important;
      border-bottom: 1px solid #e2e8f0;
      box-shadow: 0 1px 3px rgba(0,0,0,.06);
      min-height: 56px;
    }
    .app-header .navbar-nav .nav-link { color: #475569 !important; }
    .navbar-badge {
      position:absolute; top:6px; right:4px;
      font-size:.55rem; padding:.2em .4em; border-radius:10rem;
    }

    /* ── Sidebar ────────────────────────────────────── */
    .app-sidebar { background: var(--asf-navy) !important; }
    .sidebar-wrapper { background: var(--asf-navy); }
    .sidebar-brand {
      background: linear-gradient(135deg, var(--asf-indigo), var(--asf-violet));
      border-bottom: 1px solid rgba(255,255,255,.1) !important;
      padding: 14px 16px;
    }
    .sidebar-brand:hover { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
    .brand-text { font-weight: 700; font-size: .95rem; letter-spacing: -.01em; color: #fff !important; }

    /* User panel */
    .asf-user-panel { border-bottom: 1px solid rgba(255,255,255,.07); padding: 1rem; display:flex; align-items:center; }

    /* Sidebar nav items */
    .sidebar-menu > .nav-item > .nav-link {
      color: #94a3b8 !important;
      border-radius: .5rem;
      margin: 1px 8px;
      padding: 9px 14px;
      font-size: .85rem;
      font-weight: 500;
      transition: all .18s;
      display:flex; align-items:center;
    }
    .sidebar-menu > .nav-item > .nav-link:hover {
      background: rgba(99,102,241,.15) !important;
      color: #c7d2fe !important;
    }
    .sidebar-menu > .nav-item > .nav-link.active {
      background: linear-gradient(90deg, var(--asf-indigo), var(--asf-violet)) !important;
      color: #fff !important;
      box-shadow: 0 2px 8px rgba(99,102,241,.4);
    }
    .sidebar-menu .nav-icon { width: 1.1rem; text-align: center; margin-right: .5rem; font-size: .9rem; }
    .sidebar-menu .nav-link > p { margin: 0; flex: 1; }

    /* Section headers */
    .sidebar-menu .nav-header {
      color: #475569 !important;
      font-size: .65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      padding: 1rem 1.2rem .4rem !important;
      background: transparent;
    }

    /* ── Content area ───────────────────────────────── */
    .app-main { background: var(--asf-surface) !important; }
    .app-content-header { padding: 1.25rem 1.5rem .5rem; }
    .app-content-header h1 { font-size: 1.15rem; font-weight: 700; color: #1e293b; margin:0; }
    .app-content { padding: 0 .5rem 1.5rem; }
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

    /* ── Severity badges ────────────────────────────── */
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
    .app-footer {
      background: #fff;
      border-top: 1px solid #e2e8f0 !important;
      color: #94a3b8;
      font-size: .78rem;
      padding: .75rem 1.5rem;
    }

    /* ── Sidebar scrollbar ──────────────────────────── */
    .sidebar-wrapper { overflow-y: auto; scrollbar-width: thin; scrollbar-color: #334155 transparent; }
    .sidebar-wrapper::-webkit-scrollbar { width: 4px; }
    .sidebar-wrapper::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }

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
      .app-content-header { padding: .75rem 1rem .25rem; }
    }
  </style>
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

  <!-- Spinner overlay -->
  <div id="pageSpinner">
    <div class="text-center">
      <div class="spinner-border" style="color:var(--asf-indigo);width:3rem;height:3rem;" role="status"></div>
      <div class="mt-2 small text-muted">Processing…</div>
    </div>
  </div>

  <!-- ╔══════════════════════════════════════════════════╗
       ║  TOP NAVBAR                                      ║
       ╚══════════════════════════════════════════════════╝ -->
  <nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
      <!-- Left: sidebar toggle + breadcrumb -->
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
            <i class="fas fa-bars" style="color:#475569;"></i>
          </a>
        </li>
        <li class="nav-item d-none d-md-flex align-items-center ms-2">
          <ol class="breadcrumb mb-0 bg-transparent p-0">
            <li class="breadcrumb-item"><a href="home.php">Home</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($page_title ?? '') ?></li>
          </ol>
        </li>
      </ul>

      <!-- Right: notifications + user -->
      <ul class="navbar-nav ms-auto">
        <!-- Notifications -->
        <li class="nav-item dropdown">
          <a class="nav-link position-relative" data-bs-toggle="dropdown" href="#">
            <i class="far fa-bell"></i>
            <span class="badge bg-warning navbar-badge" id="notifBadge" style="display:none;">!</span>
          </a>
          <div class="dropdown-menu dropdown-menu-end" style="border-radius:.75rem;border:none;box-shadow:0 8px 24px rgba(0,0,0,.12);min-width:280px;">
            <span class="dropdown-header" style="font-weight:700;font-size:.78rem;color:#64748b;">NOTIFICATIONS</span>
            <div class="dropdown-divider"></div>
            <div id="notifList" class="px-3 py-2 text-muted small">No new notifications</div>
          </div>
        </li>

        <!-- User menu -->
        <li class="nav-item dropdown">
          <a class="nav-link" data-bs-toggle="dropdown" href="#">
            <span class="d-flex align-items-center" style="gap:.5rem;">
              <span style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));display:flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:700;">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
              </span>
              <span class="d-none d-md-inline" style="font-size:.83rem;font-weight:600;color:#1e293b;">
                <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
              </span>
              <i class="fas fa-chevron-down" style="font-size:.65rem;color:#94a3b8;"></i>
            </span>
          </a>
          <div class="dropdown-menu dropdown-menu-end" style="border-radius:.75rem;border:none;box-shadow:0 8px 24px rgba(0,0,0,.12);min-width:200px;">
            <div class="px-3 py-2 border-bottom">
              <div style="font-weight:700;font-size:.85rem;color:#1e293b;"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
              <div style="font-size:.72rem;color:#64748b;"><?= ucfirst($_SESSION['user_role'] ?? 'analyst') ?></div>
            </div>
            <a href="change_password.php" class="dropdown-item py-2"><i class="fas fa-key fa-fw me-2 text-muted"></i>Change Password</a>
            <a href="settings.php" class="dropdown-item py-2"><i class="fas fa-cog fa-fw me-2 text-muted"></i>Settings</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="dropdown-item py-2 text-danger"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Sign Out</a>
          </div>
        </li>
      </ul>
    </div>
  </nav>

  <!-- ╔══════════════════════════════════════════════════╗
       ║  SIDEBAR                                         ║
       ╚══════════════════════════════════════════════════╝ -->
  <aside class="app-sidebar shadow" data-bs-theme="dark">

    <!-- Brand -->
    <div class="sidebar-brand">
      <a href="home.php" class="brand-link" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="30" height="30" style="flex-shrink:0;">
          <path d="M60 12 L94 26 L94 58 Q94 84 60 100 Q26 84 26 58 L26 26 Z"
                fill="none" stroke="white" stroke-width="5" stroke-linejoin="round" stroke-linecap="round"/>
          <rect x="48" y="58" width="24" height="18" rx="4" fill="white"/>
          <path d="M52 58 L52 50 Q52 42 60 42 Q68 42 68 50 L68 58" fill="none" stroke="white" stroke-width="4" stroke-linecap="round"/>
          <circle cx="60" cy="66" r="3" fill="#6366f1"/>
          <rect x="58.5" y="66" width="3" height="5" rx="1.5" fill="#6366f1"/>
        </svg>
        <span class="brand-text">AutoSecForge <span style="color:#c7d2fe;font-weight:400;font-size:.75rem;">Pro</span></span>
      </a>
    </div>

    <div class="sidebar-wrapper">
      <!-- User panel -->
      <div class="asf-user-panel">
        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem;flex-shrink:0;">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="ms-2">
          <a href="change_password.php" style="color:#c7d2fe;font-weight:600;font-size:.82rem;text-decoration:none;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></a>
          <div style="color:#475569;font-size:.7rem;"><?= ucfirst($_SESSION['user_role'] ?? 'analyst') ?></div>
        </div>
      </div>

      <!-- Nav -->
      <nav class="mt-1">
        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Main navigation" data-accordion="false">

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
              <span class="badge rounded-pill" style="background:var(--asf-indigo);color:#fff;font-size:.65rem;">AI</span>
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
       ║  MAIN CONTENT                                    ║
       ╚══════════════════════════════════════════════════╝ -->
  <main class="app-main">
    <!-- Page header -->
    <div class="app-content-header">
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
    <div class="app-content">
      <div class="container-fluid">
