<?php
require_once __DIR__ . '/../src/auth.php';
require_auth();

$stats = [
    'total_scans' => 42,
    'critical'    => 3,
    'high'        => 7,
    'medium'      => 12,
    'low'         => 5,
];

$role = $_SESSION['user_role'] ?? 'viewer';
$canManageUsers = in_array($role, ['admin', 'manager']);
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoSecForge Pro – Dashboard</title>
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/overlayscrollbars.min.css" rel="stylesheet">
    <link href="/assets/css/adminlte.min.css" rel="stylesheet">
    <script src="/assets/js/chart.umd.min.js"></script>
    <style>
        body{background:#f4f6f9}
        .stat-card{background:white;border-radius:0.75rem;box-shadow:0 2px 4px rgba(0,0,0,0.05);border:none;transition:transform 0.2s;height:100%}
        .stat-card:hover{transform:translateY(-3px)}
        .stat-value{font-size:1.75rem;font-weight:700}
        .stat-icon{font-size:2rem;opacity:0.6}
        .content-card{background:white;border-radius:0.75rem;box-shadow:0 2px 4px rgba(0,0,0,0.05);margin-bottom:1.5rem;border:none}
        .card-header-custom{background:transparent;border-bottom:1px solid #e2e8f0;padding:0.75rem 1.25rem;font-weight:600}
        .tool-card{background:#f8fafc;border-radius:0.5rem;border:1px solid #e2e8f0;text-align:center;padding:0.75rem;cursor:pointer;transition:all 0.2s}
        .tool-card:hover{background:#eef2ff;border-color:#3b82f6;transform:translateY(-2px)}
        .tool-card i{font-size:1.5rem}
        .user-avatar{background:#3b82f6;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold}
        @media (max-width:768px){.stat-value{font-size:1.25rem}.stat-icon{font-size:1.5rem}}
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="app-header navbar navbar-expand bg-white">
        <div class="container-fluid">
            <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li></ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" href="#">
                        <div class="user-avatar"><?php echo strtoupper(substr($userName,0,2)); ?></div>
                        <span><?php echo $userName; ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="dropdown-header"><strong><?php echo $userName; ?></strong><br><small><?php echo $userEmail; ?></small></div>
                        <div class="dropdown-divider"></div>
                        <a href="change_password.php" class="dropdown-item"><i class="bi bi-key"></i> Change Password</a>
                        <a href="settings.php" class="dropdown-item"><i class="bi bi-gear"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
    <aside class="app-sidebar bg-body-secondary shadow">
        <div class="sidebar-brand"><a href="dashboard.php" class="brand-link d-flex align-items-center gap-2"><div class="brand-logo-icon bg-primary rounded-circle p-1" style="width:32px;height:32px;"><i class="bi bi-shield-lock-fill text-white"></i></div><span class="brand-text fw-bold">AutoSecForge</span></a></div>
        <div class="sidebar-wrapper">
            <ul class="sidebar-menu">
                <li class="nav-item"><a href="dashboard.php" class="nav-link active"><i class="bi bi-speedometer2"></i><p>Dashboard</p></a></li>
                <li class="nav-item"><a href="#" class="nav-link" onclick="alert('Scan History coming soon'); return false;"><i class="bi bi-clock-history"></i><p>Scan History</p></a></li>
                <li class="nav-item"><a href="#" class="nav-link" onclick="alert('SonarQube upload coming soon'); return false;"><i class="bi bi-upload"></i><p>SonarQube Upload</p></a></li>
                <?php if ($canManageUsers): ?><li class="nav-item"><a href="#" class="nav-link" onclick="alert('User management coming soon'); return false;"><i class="bi bi-people"></i><p>Manage Users</p></a></li><?php endif; ?>
                <li class="nav-item"><a href="#" class="nav-link" onclick="alert('Settings page coming soon'); return false;"><i class="bi bi-gear"></i><p>Settings</p></a></li>
            </ul>
            <div class="sidebar-status mt-4 px-3">
                <div class="small text-uppercase text-secondary mb-2">System Status</div>
                <div class="status-item d-flex align-items-center gap-2 mb-2"><div class="status-dot" style="width:8px;height:8px;background:#10b981;border-radius:50%;"></div><span>API Online</span></div>
                <div class="status-item d-flex align-items-center gap-2"><div class="status-dot" style="width:8px;height:8px;background:#10b981;border-radius:50%;"></div><span>Scanner Ready</span></div>
            </div>
        </div>
    </aside>
    <div class="app-main">
        <div class="app-content-header"><div class="container-fluid"><div class="row"><div class="col-sm-6"><h1 class="mb-0">Dashboard</h1><p class="text-muted">Welcome back, <?php echo $userName; ?></p></div><div class="col-sm-6"><ol class="breadcrumb float-sm-end"><li class="breadcrumb-item"><a href="#">Home</a></li><li class="breadcrumb-item active">Dashboard</li></ol></div></div></div></div>
        <div class="app-content"><div class="container-fluid">
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-lg-3"><div class="stat-card card p-3"><div class="d-flex justify-content-between align-items-start"><div><div class="text-secondary text-uppercase small fw-bold">Total Scans</div><div class="stat-value"><?php echo $stats['total_scans']; ?></div></div><i class="bi bi-search-heart stat-icon text-primary"></i></div><div class="mt-2"><span class="small text-success"><i class="bi bi-arrow-up"></i> 12%</span> <span class="text-secondary">since last month</span></div></div></div>
                <div class="col-12 col-sm-6 col-lg-3"><div class="stat-card card p-3"><div class="d-flex justify-content-between align-items-start"><div><div class="text-secondary text-uppercase small fw-bold">Critical</div><div class="stat-value text-danger"><?php echo $stats['critical']; ?></div></div><i class="bi bi-exclamation-triangle stat-icon text-danger"></i></div><div class="mt-2"><span class="small text-danger"><i class="bi bi-arrow-up"></i> 5%</span> <span class="text-secondary">higher</span></div></div></div>
                <div class="col-12 col-sm-6 col-lg-3"><div class="stat-card card p-3"><div class="d-flex justify-content-between align-items-start"><div><div class="text-secondary text-uppercase small fw-bold">High Severity</div><div class="stat-value text-warning"><?php echo $stats['high']; ?></div></div><i class="bi bi-shield-exclamation stat-icon text-warning"></i></div><div class="mt-2"><span class="small text-warning"><i class="bi bi-arrow-down"></i> 2%</span> <span class="text-secondary">improved</span></div></div></div>
                <div class="col-12 col-sm-6 col-lg-3"><div class="stat-card card p-3"><div class="d-flex justify-content-between align-items-start"><div><div class="text-secondary text-uppercase small fw-bold">Other Findings</div><div class="stat-value"><?php echo $stats['medium']+$stats['low']; ?></div></div><i class="bi bi-clipboard-data stat-icon text-info"></i></div><div class="mt-2"><span class="small text-info"><i class="bi bi-arrow-up"></i> 8%</span> <span class="text-secondary">new</span></div></div></div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-8"><div class="content-card"><div class="card-header-custom d-flex justify-content-between align-items-center"><span><i class="bi bi-graph-up me-2"></i> Scan Trends (Last 6 Months)</span><button class="btn btn-sm btn-outline-secondary" onclick="alert('Export coming soon')"><i class="bi bi-download"></i> Export</button></div><div class="p-3"><canvas id="trendChart" height="250"></canvas></div></div></div>
                <div class="col-12 col-lg-4"><div class="content-card"><div class="card-header-custom"><i class="bi bi-pie-chart me-2"></i> Severity Distribution</div><div class="p-3"><canvas id="pieChart" height="250"></canvas></div></div></div>
            </div>
            <div class="content-card"><div class="card-header-custom"><i class="bi bi-lightning-charge me-2"></i> Quick Security Scan</div><div class="p-3"><div class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label">Scan Type</label><select id="scanType" class="form-select"><optgroup label="Network Security"><option value="network">Nmap – Network Scan</option></optgroup><optgroup label="Web Security"><option value="nikto">Nikto – Web Scan</option><option value="sqlmap">SQLMap – SQL Injection</option><option value="zap">ZAP – DAST</option></optgroup><optgroup label="Container Security"><option value="trivy">Trivy – Container Scan</option></optgroup></select></div><div class="col-md-6"><label class="form-label">Target (IP / URL / Image)</label><input type="text" id="target" class="form-control" placeholder="e.g., scanme.nmap.org or nginx:latest"></div><div class="col-md-2"><button id="runScanBtn" class="btn btn-primary w-100"><i class="bi bi-play-fill"></i> Run Scan</button></div></div><div id="scanResult" class="mt-4" style="display:none"><div class="alert alert-info"><span class="spinner-border spinner-border-sm"></span> Scanning...</div><pre class="mt-3 bg-light p-3 rounded border"></pre></div></div></div>
            <div class="content-card mt-4"><div class="card-header-custom"><i class="bi bi-tools me-2"></i> Integrated Security Tools</div><div class="p-3"><div class="row g-3"><div class="col-6 col-md-3 col-lg-2"><div class="tool-card" onclick="setScanType('nmap')"><i class="bi bi-hdd-stack text-primary"></i><div class="mt-1 fw-bold">Nmap</div><small class="text-secondary">Network</small></div></div><div class="col-6 col-md-3 col-lg-2"><div class="tool-card" onclick="setScanType('nikto')"><i class="bi bi-globe2 text-success"></i><div class="mt-1 fw-bold">Nikto</div><small class="text-secondary">Web</small></div></div><div class="col-6 col-md-3 col-lg-2"><div class="tool-card" onclick="setScanType('sqlmap')"><i class="bi bi-database text-danger"></i><div class="mt-1 fw-bold">SQLMap</div><small class="text-secondary">Injection</small></div></div><div class="col-6 col-md-3 col-lg-2"><div class="tool-card" onclick="setScanType('zap')"><i class="bi bi-shield-shaded text-warning"></i><div class="mt-1 fw-bold">ZAP</div><small class="text-secondary">DAST</small></div></div><div class="col-6 col-md-3 col-lg-2"><div class="tool-card" onclick="setScanType('trivy')"><i class="bi bi-box text-info"></i><div class="mt-1 fw-bold">Trivy</div><small class="text-secondary">Container</small></div></div><div class="col-6 col-md-3 col-lg-2"><div class="tool-card" onclick="window.location.href='upload_sonar.php'"><i class="bi bi-code-slash text-secondary"></i><div class="mt-1 fw-bold">SonarQube</div><small class="text-secondary">SAST</small></div></div></div></div></div>
            <div class="content-card mt-4"><div class="card-header-custom"><i class="bi bi-file-earmark-text me-2"></i> Generate Report</div><div class="p-3"><div class="d-flex flex-wrap gap-2"><button class="btn btn-sm btn-outline-secondary" onclick="generateReport('pdf')"><i class="bi bi-file-pdf"></i> PDF</button><button class="btn btn-sm btn-outline-secondary" onclick="generateReport('docx')"><i class="bi bi-file-word"></i> Word</button><button class="btn btn-sm btn-outline-secondary" onclick="generateReport('xlsx')"><i class="bi bi-file-excel"></i> Excel</button><button class="btn btn-sm btn-outline-secondary" onclick="generateReport('json')"><i class="bi bi-file-code"></i> JSON</button></div><div id="reportResult" class="mt-3 text-muted" style="display:none"></div></div></div>
        </div></div>
    </div>
</div>
<script src="/assets/js/popper.min.js"></script>
<script src="/assets/js/bootstrap.min.js"></script>
<script src="/assets/js/overlayscrollbars.browser.es6.min.js"></script>
<script src="/assets/js/adminlte.min.js"></script>
<script>
    new Chart(document.getElementById('trendChart'),{type:'line',data:{labels:['Jan','Feb','Mar','Apr','May','Jun'],datasets:[{label:'Scans',data:[12,19,15,25,30,<?php echo $stats['total_scans']; ?>],borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,0.1)',fill:true,tension:0.3}]},options:{responsive:true,maintainAspectRatio:true}});
    new Chart(document.getElementById('pieChart'),{type:'doughnut',data:{labels:['Critical','High','Medium','Low'],datasets:[{data:[<?php echo $stats['critical']; ?>,<?php echo $stats['high']; ?>,<?php echo $stats['medium']; ?>,<?php echo $stats['low']; ?>],backgroundColor:['#ef4444','#f59e0b','#3b82f6','#10b981']}]},options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom'}}}});
    document.getElementById('runScanBtn').addEventListener('click',async()=>{const type=document.getElementById('scanType').value,target=document.getElementById('target').value.trim(),resDiv=document.getElementById('scanResult');if(!target){alert('Enter a target');return}resDiv.style.display='block';const msgDiv=resDiv.querySelector('.alert'),pre=resDiv.querySelector('pre');msgDiv.className='alert alert-info';msgDiv.innerHTML='<span class="spinner-border spinner-border-sm"></span> Running scan...';pre.textContent='';let endpoint,body;if(type==='trivy'){endpoint='http://localhost:8081/scan/trivy';body={image:target}}else if(type==='network'){endpoint='http://localhost:8081/scan/network';body={target:target}}else{endpoint=`http://localhost:8081/scan/${type}`;body={url:target}}try{const resp=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const data=await resp.json();msgDiv.className='alert alert-success';msgDiv.innerHTML='<i class="bi bi-check-circle-fill"></i> Scan completed';pre.textContent=JSON.stringify(data,null,2)}catch(err){msgDiv.className='alert alert-danger';msgDiv.innerHTML='<i class="bi bi-x-circle-fill"></i> Error: '+err.message}});
    function setScanType(type){const sel=document.getElementById('scanType');if(type==='nmap')sel.value='network';else if(type==='nikto')sel.value='nikto';else if(type==='sqlmap')sel.value='sqlmap';else if(type==='trivy')sel.value='trivy';else if(type==='zap')sel.value='zap';document.getElementById('target').focus()}
    function generateReport(format){const div=document.getElementById('reportResult');div.style.display='block';div.innerHTML=`<span class="spinner-border spinner-border-sm me-2"></span> Generating ${format.toUpperCase()} report...`;setTimeout(()=>div.innerHTML=`<span class="text-success"><i class="bi bi-check-circle-fill me-2"></i>${format.toUpperCase()} report generated. <a href="#">Download</a></span>`,2000)}
    OverlayScrollbars(document.querySelector('.sidebar-wrapper'),{scrollbars:{theme:'os-theme-dark',autoHide:'leave'}});
</script>
</body>
</html>
