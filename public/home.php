<?php
require_once '../src/auth.php';
require_auth();
$page_title = 'Dashboard';
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'User');
// Example stats – replace with DB data
$stats = ['total_scans'=>347, 'critical'=>5, 'high'=>12, 'medium'=>28, 'low'=>43, 'fixed'=>34, 'compliance'=>87];
?>
<?php require_once '../views/partials/header.php'; ?>

<!-- Welcome banner -->
<div class="bg-primary text-white p-4 rounded-4 mb-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div><h1 class="h3 fw-bold mb-1">Welcome back, <?= $userName ?>!</h1><p class="mb-0 opacity-75">Your security posture at a glance.</p></div>
        <div><i class="fas fa-shield-alt fa-3x opacity-50"></i></div>
    </div>
</div>

<!-- Stats cards responsive row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card p-3 text-center"><i class="fas fa-chart-line fa-2x text-primary mb-2"></i><h4 class="mb-0"><?= number_format($stats['total_scans']) ?></h4><small class="text-muted">Total Scans</small></div></div>
    <div class="col-6 col-md-3"><div class="card p-3 text-center"><i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i><h4 class="mb-0 text-danger"><?= $stats['critical'] ?></h4><small class="text-muted">Critical</small></div></div>
    <div class="col-6 col-md-3"><div class="card p-3 text-center"><i class="fas fa-check-circle fa-2x text-success mb-2"></i><h4 class="mb-0 text-success"><?= $stats['fixed'] ?></h4><small class="text-muted">Fixed (30d)</small></div></div>
    <div class="col-6 col-md-3"><div class="card p-3 text-center"><i class="fas fa-clipboard-list fa-2x text-info mb-2"></i><h4 class="mb-0 text-info"><?= $stats['compliance'] ?>%</h4><small class="text-muted">Compliance</small></div></div>
</div>

<!-- Charts row (responsive) -->
<div class="row g-3 mb-4">
    <div class="col-lg-8"><div class="card p-3"><h5>Security Trends</h5><canvas id="trendChart" style="height: 280px;"></canvas></div></div>
    <div class="col-lg-4"><div class="card p-3"><h5>Severity</h5><canvas id="severityChart" style="height: 220px;"></canvas><div class="row text-center mt-3 small"><div class="col-3"><span class="text-danger">●</span> <?= $stats['critical'] ?></div><div class="col-3"><span class="text-warning">●</span> <?= $stats['high'] ?></div><div class="col-3"><span class="text-info">●</span> <?= $stats['medium'] ?></div><div class="col-3"><span class="text-secondary">●</span> <?= $stats['low'] ?></div></div></div></div>
</div>

<!-- Quick actions (responsive) -->
<div class="card p-3"><h5>Quick Actions</h5><div class="row g-2"><div class="col-6 col-md-3"><a href="scan_trigger.php" class="btn btn-outline-primary w-100 py-2"><i class="fas fa-bolt me-1"></i> New Scan</a></div><div class="col-6 col-md-3"><a href="report.php" class="btn btn-outline-success w-100 py-2"><i class="fas fa-file-alt me-1"></i> Report</a></div><div class="col-6 col-md-3"><a href="clients.php" class="btn btn-outline-info w-100 py-2"><i class="fas fa-users me-1"></i> Clients</a></div><div class="col-6 col-md-3"><a href="checklist.php" class="btn btn-outline-warning w-100 py-2"><i class="fas fa-list-check me-1"></i> Compliance</a></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('trendChart'), { type:'line', data:{ labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], datasets:[{ label:'Scans', data:[12,19,15,25,30,42,48,55,62,70,78,<?= $stats['total_scans'] ?>], borderColor:'#3b82f6', fill:true, tension:0.3 }] } });
    new Chart(document.getElementById('severityChart'), { type:'doughnut', data:{ labels:['Critical','High','Medium','Low'], datasets:[{ data:[<?= $stats['critical'] ?>,<?= $stats['high'] ?>,<?= $stats['medium'] ?>,<?= $stats['low'] ?>], backgroundColor:['#ef4444','#f59e0b','#3b82f6','#6b7280'] }] }, options:{ cutout:'60%', plugins:{ legend:{ position:'bottom' } } } });
</script>

<?php require_once '../views/partials/footer.php'; ?>
