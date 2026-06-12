<?php
require_once '../src/auth.php';
require_auth();
$page_title = 'Dashboard';

// ── Fetch live stats ───────────────────────────────────────────────
$stats = ['total_scans'=>0,'completed'=>0,'partial'=>0,'failed'=>0];
$recent_scans = [];
$chart_labels = [];
$chart_data   = [];

try {
    $db = Database::getInstance();

    $row = $db->query("SELECT
        COUNT(*) AS total,
        SUM(status='completed') AS completed,
        SUM(status='partial')   AS partial,
        SUM(status='failed')    AS failed
      FROM scan_jobs")->fetch(PDO::FETCH_ASSOC);
    if ($row) $stats = array_merge($stats, array_filter($row, fn($v) => $v !== null));

    $recent_scans = $db->query(
        "SELECT j.id, j.target, j.scan_types, j.status, j.model, j.created_at, u.full_name AS analyst
           FROM scan_jobs j LEFT JOIN users u ON u.id = j.triggered_by
          ORDER BY j.created_at DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);

    // 7-day chart data
    $days = $db->query("SELECT DATE(created_at) d, COUNT(*) n
      FROM scan_jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      GROUP BY d ORDER BY d")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($days as $d) {
        $chart_labels[] = date('D j', strtotime($d['d']));
        $chart_data[]   = (int)$d['n'];
    }
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}

// ── Check tool health (non-fatal) ──────────────────────────────────
function ping_service(string $url): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>1, CURLOPT_TIMEOUT=>2, CURLOPT_NOBODY=>0]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $c >= 200 && $c < 400;
}

$env = @parse_ini_file('/var/www/html/.env', false, INI_SCANNER_RAW) ?: [];
$tools = [
    ['name'=>'MCP Router',    'icon'=>'fas fa-route',    'color'=>'#6366f1', 'url'=>($env['MCP_URL']??'http://mcp-hackstrike:6300').'/health'],
    ['name'=>'AI Agent',      'icon'=>'fas fa-robot',    'color'=>'#8b5cf6', 'url'=>($env['AI_AGENT_URL']??'http://openai-free-agents:6400').'/health'],
    ['name'=>'OWASP ZAP',     'icon'=>'fas fa-spider',   'color'=>'#3b82f6', 'url'=>'http://zap:8090'],
    ['name'=>'SonarQube',     'icon'=>'fas fa-code',     'color'=>'#0ea5e9', 'url'=>'http://sonarqube:9000'],
    ['name'=>'MobSF',         'icon'=>'fas fa-mobile-alt','color'=>'#10b981','url'=>'http://mobsf:8000'],
    ['name'=>'Trivy',         'icon'=>'fas fa-shield-alt','color'=>'#f59e0b','url'=>'http://trivy:8081/health'],
];
foreach ($tools as &$t) {
    $t['online'] = ping_service($t['url']);
}
unset($t);

$online_count = count(array_filter($tools, fn($t) => $t['online']));

$status_badge = fn($s) => match($s) {
    'completed' => '<span class="badge" style="background:#dcfce7;color:#16a34a;font-size:.7rem;">Completed</span>',
    'partial'   => '<span class="badge" style="background:#fef3c7;color:#d97706;font-size:.7rem;">Partial</span>',
    'failed'    => '<span class="badge" style="background:#fee2e2;color:#dc2626;font-size:.7rem;">Failed</span>',
    default     => '<span class="badge badge-secondary" style="font-size:.7rem;">'.htmlspecialchars($s).'</span>',
};
?>
<?php require_once '../views/partials/header.php'; ?>

<?php if (isset($db_error)): ?>
<div class="alert alert-warning d-flex align-items-center" style="border-radius:.75rem;">
  <i class="fas fa-exclamation-triangle mr-2"></i>
  <div>DB not ready — <code><?= htmlspecialchars($db_error) ?></code>.
  Run <code>database/schema.sql</code> to initialise tables.</div>
</div>
<?php endif; ?>

<!-- ── KPI Row ──────────────────────────────────────────────────── -->
<div class="row">

  <div class="col-6 col-xl-3 mb-4">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
          <div class="stat-label">Total Scans</div>
          <div class="stat-value mt-1"><?= number_format((int)$stats['total_scans']) ?></div>
          <div class="stat-trend mt-1" style="color:#16a34a;"><i class="fas fa-arrow-up mr-1"></i>All-time</div>
        </div>
        <div class="stat-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
          <i class="fas fa-chart-bar"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3 mb-4">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
          <div class="stat-label">Completed</div>
          <div class="stat-value mt-1" style="color:#16a34a;"><?= (int)$stats['completed'] ?></div>
          <div class="stat-trend mt-1" style="color:#16a34a;"><i class="fas fa-check-circle mr-1"></i>Clean</div>
        </div>
        <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">
          <i class="fas fa-check-double"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3 mb-4">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
          <div class="stat-label">Tools Online</div>
          <div class="stat-value mt-1"><?= $online_count ?> / <?= count($tools) ?></div>
          <div class="stat-trend mt-1" style="color:<?= $online_count >= 4 ? '#16a34a' : '#d97706'; ?>;">
            <i class="fas fa-circle mr-1" style="font-size:.5rem;"></i>
            <?= $online_count >= count($tools) ? 'All systems go' : ($online_count >= 3 ? 'Mostly online' : 'Check services') ?>
          </div>
        </div>
        <div class="stat-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
          <i class="fas fa-server"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3 mb-4">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
          <div class="stat-label">AI Model</div>
          <div class="stat-value mt-1" style="font-size:1.1rem;"><?= htmlspecialchars($env['OLLAMA_MODEL'] ?? 'llama3') ?></div>
          <div class="stat-trend mt-1" style="color:#6366f1;"><i class="fas fa-robot mr-1"></i>Local LLM</div>
        </div>
        <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
          <i class="fas fa-brain"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Charts Row ───────────────────────────────────────────────── -->
<div class="row">
  <div class="col-lg-8 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title">Scan Activity (Last 7 Days)</span>
        <a href="scan_trigger.php" class="btn btn-asf btn-sm px-3"><i class="fas fa-plus mr-1"></i>New Scan</a>
      </div>
      <div class="card-body">
        <canvas id="trendChart" height="120"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-4 mb-4">
    <div class="card h-100">
      <div class="card-header"><span class="card-title">Scan Status</span></div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <canvas id="statusChart" width="200" height="200"></canvas>
        <div class="mt-3 w-100">
          <div class="d-flex justify-content-between mb-1 small">
            <span><span class="badge mr-1" style="background:#dcfce7;color:#16a34a;">●</span>Completed</span>
            <strong><?= (int)$stats['completed'] ?></strong>
          </div>
          <div class="d-flex justify-content-between mb-1 small">
            <span><span class="badge mr-1" style="background:#fef3c7;color:#d97706;">●</span>Partial</span>
            <strong><?= (int)$stats['partial'] ?></strong>
          </div>
          <div class="d-flex justify-content-between small">
            <span><span class="badge mr-1" style="background:#fee2e2;color:#dc2626;">●</span>Failed</span>
            <strong><?= (int)$stats['failed'] ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Tool Status Row ──────────────────────────────────────────── -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-server mr-2" style="color:var(--asf-indigo);"></i>Tool Status</span></div>
      <div class="card-body">
        <div class="row">
          <?php foreach ($tools as $t): ?>
          <div class="col-6 col-md-4 col-xl-2 mb-3">
            <div class="border rounded-lg p-3 text-center" style="border-color:#f1f5f9 !important;transition:all .2s;" onmouseover="this.style.borderColor='<?= $t['color'] ?>'" onmouseout="this.style.borderColor='#f1f5f9'">
              <div style="width:40px;height:40px;border-radius:.625rem;background:<?= $t['color'] ?>22;display:inline-flex;align-items:center;justify-content:center;margin-bottom:.5rem;">
                <i class="<?= $t['icon'] ?>" style="color:<?= $t['color'] ?>;font-size:1rem;"></i>
              </div>
              <div style="font-size:.78rem;font-weight:600;color:#1e293b;"><?= htmlspecialchars($t['name']) ?></div>
              <div class="mt-1">
                <?php if ($t['online']): ?>
                  <span style="font-size:.68rem;color:#16a34a;font-weight:600;"><i class="fas fa-circle mr-1" style="font-size:.45rem;"></i>Online</span>
                <?php else: ?>
                  <span style="font-size:.68rem;color:#94a3b8;font-weight:500;"><i class="fas fa-circle mr-1" style="font-size:.45rem;"></i>Offline</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Recent Scans ─────────────────────────────────────────────── -->
<div class="row">
  <div class="col-12 mb-4">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title"><i class="fas fa-history mr-2" style="color:var(--asf-indigo);"></i>Recent Security Reviews</span>
        <a href="scan_jobs.php" class="btn btn-sm btn-outline-secondary">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recent_scans)): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-search fa-2x mb-2 d-block opacity-50"></i>
          No scans yet. <a href="scan_trigger.php">Run your first security review</a>.
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th style="width:50px;">#</th>
                <th>Target</th>
                <th>Modules</th>
                <th>Model</th>
                <th>Status</th>
                <th>Analyst</th>
                <th>Date</th>
                <th style="width:80px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_scans as $job): ?>
              <tr>
                <td class="text-muted"><?= (int)$job['id'] ?></td>
                <td><code style="font-size:.78rem;"><?= htmlspecialchars($job['target']) ?></code></td>
                <td>
                  <?php foreach (explode(',', $job['scan_types']) as $t): ?>
                    <span class="badge badge-secondary mr-1" style="font-size:.65rem;"><?= htmlspecialchars(trim($t)) ?></span>
                  <?php endforeach; ?>
                </td>
                <td><small class="text-muted"><?= htmlspecialchars($job['model'] ?? '—') ?></small></td>
                <td><?= $status_badge($job['status']) ?></td>
                <td><small><?= htmlspecialchars($job['analyst'] ?? '—') ?></small></td>
                <td><small class="text-muted"><?= htmlspecialchars(substr($job['created_at'] ?? '', 0, 16)) ?></small></td>
                <td>
                  <a href="scan_jobs.php?detail=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-primary px-2 py-1" title="View">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Quick Actions ────────────────────────────────────────────── -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header"><span class="card-title">Quick Actions</span></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-6 col-md-3 col-xl-2 mb-2">
            <a href="scan_trigger.php" class="btn btn-asf btn-block py-3 d-flex flex-column align-items-center">
              <i class="fas fa-bolt fa-lg mb-1"></i><small>New Scan</small>
            </a>
          </div>
          <div class="col-6 col-md-3 col-xl-2 mb-2">
            <a href="report.php" class="btn btn-block py-3 d-flex flex-column align-items-center" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:.625rem;">
              <i class="fas fa-file-alt fa-lg mb-1"></i><small>Reports</small>
            </a>
          </div>
          <div class="col-6 col-md-3 col-xl-2 mb-2">
            <a href="oasm.php" class="btn btn-block py-3 d-flex flex-column align-items-center" style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;border-radius:.625rem;">
              <i class="fas fa-crosshairs fa-lg mb-1"></i><small>Attack Surface</small>
            </a>
          </div>
          <div class="col-6 col-md-3 col-xl-2 mb-2">
            <a href="checklist.php" class="btn btn-block py-3 d-flex flex-column align-items-center" style="background:#fffbeb;color:#d97706;border:1px solid #fde68a;border-radius:.625rem;">
              <i class="fas fa-clipboard-check fa-lg mb-1"></i><small>Compliance</small>
            </a>
          </div>
          <div class="col-6 col-md-3 col-xl-2 mb-2">
            <a href="review.php" class="btn btn-block py-3 d-flex flex-column align-items-center" style="background:#fdf4ff;color:#9333ea;border:1px solid #e9d5ff;border-radius:.625rem;">
              <i class="fas fa-search-plus fa-lg mb-1"></i><small>Findings</small>
            </a>
          </div>
          <div class="col-6 col-md-3 col-xl-2 mb-2">
            <a href="deliverables.php" class="btn btn-block py-3 d-flex flex-column align-items-center" style="background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;border-radius:.625rem;">
              <i class="fas fa-box-open fa-lg mb-1"></i><small>Deliverables</small>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$chart_labels_json = json_encode($chart_labels ?: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']);
$chart_data_json   = json_encode($chart_data   ?: [0,0,0,0,0,0,0]);

$page_scripts = <<<SCRIPTS
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Trend chart
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: $chart_labels_json,
    datasets: [{
      label: 'Scans',
      data: $chart_data_json,
      borderColor: '#6366f1',
      backgroundColor: 'rgba(99,102,241,.1)',
      fill: true,
      tension: 0.4,
      pointBackgroundColor: '#6366f1',
      pointRadius: 4,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1, color: '#94a3b8', font: { size: 11 } }, grid: { color: '#f1f5f9' } },
      x: { ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { display: false } }
    }
  }
});

// Status donut
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: ['Completed','Partial','Failed'],
    datasets: [{ data: [{$stats['completed']},{$stats['partial']},{$stats['failed']}], backgroundColor: ['#16a34a','#d97706','#dc2626'], borderWidth: 0 }]
  },
  options: {
    cutout: '70%',
    plugins: { legend: { display: false } }
  }
});
</script>
SCRIPTS;
?>

<?php require_once '../views/partials/footer.php'; ?>
