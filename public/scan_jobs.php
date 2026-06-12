<?php
require_once '../src/auth.php';
require_auth();
$page_title = 'Scan History';

// ── Modal detail AJAX ─────────────────────────────────────────────
if (isset($_GET['detail']) && ctype_digit($_GET['detail'])) {
    header('Content-Type: application/json');
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM scan_jobs WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_GET['detail']]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: ['error'=>'Not found']);
    } catch (Throwable $e) { echo json_encode(['error'=>$e->getMessage()]); }
    exit;
}

// ── Fetch jobs ────────────────────────────────────────────────────
$jobs = []; $db_error = null;
try {
    $pdo = Database::getInstance();
    if (($_SESSION['user_role'] ?? '') === 'analyst') {
        $stmt = $pdo->prepare(
            'SELECT j.*, u.full_name analyst FROM scan_jobs j
               LEFT JOIN users u ON u.id=j.triggered_by
              WHERE j.triggered_by=? ORDER BY j.created_at DESC LIMIT 200'
        );
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        $stmt = $pdo->query(
            'SELECT j.*, u.full_name analyst FROM scan_jobs j
               LEFT JOIN users u ON u.id=j.triggered_by
              ORDER BY j.created_at DESC LIMIT 500'
        );
    }
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $db_error = $e->getMessage(); }
?>
<?php require_once '../views/partials/header.php'; ?>

<div id="pageActions">
  <a href="scan_trigger.php" class="btn btn-asf btn-sm px-3">
    <i class="fas fa-plus mr-1"></i>New Scan
  </a>
</div>

<?php if ($db_error): ?>
<div class="alert alert-danger">
  <i class="fas fa-exclamation-triangle mr-2"></i>
  DB error: <?= htmlspecialchars($db_error) ?> — run <code>database/schema.sql</code>.
</div>
<?php endif; ?>

<!-- Summary stats -->
<?php if (!empty($jobs)): ?>
<?php
$total  = count($jobs);
$done   = count(array_filter($jobs, fn($j)=>$j['status']==='completed'));
$part   = count(array_filter($jobs, fn($j)=>$j['status']==='partial'));
$failed = count(array_filter($jobs, fn($j)=>$j['status']==='failed'));
?>
<div class="row mb-4">
  <div class="col-6 col-md-3 mb-2">
    <div class="card text-center py-3">
      <div style="font-size:1.6rem;font-weight:800;color:#1e293b;"><?= $total ?></div>
      <div style="font-size:.72rem;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.04em;">Total Jobs</div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <div class="card text-center py-3">
      <div style="font-size:1.6rem;font-weight:800;color:#16a34a;"><?= $done ?></div>
      <div style="font-size:.72rem;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.04em;">Completed</div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <div class="card text-center py-3">
      <div style="font-size:1.6rem;font-weight:800;color:#d97706;"><?= $part ?></div>
      <div style="font-size:.72rem;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.04em;">Partial</div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <div class="card text-center py-3">
      <div style="font-size:1.6rem;font-weight:800;color:#dc2626;"><?= $failed ?></div>
      <div style="font-size:.72rem;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.04em;">Failed</div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="card-title"><i class="fas fa-history mr-2" style="color:var(--asf-indigo);"></i>All Security Reviews</span>
    <div class="d-flex" style="gap:.5rem;">
      <input type="text" id="searchBox" class="form-control form-control-sm" placeholder="Search target…" style="width:180px;border-radius:.5rem;">
      <select id="filterStatus" class="form-control form-control-sm" style="width:120px;border-radius:.5rem;">
        <option value="">All status</option>
        <option value="completed">Completed</option>
        <option value="partial">Partial</option>
        <option value="failed">Failed</option>
      </select>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($jobs)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-folder-open fa-2x d-block mb-2 opacity-50"></i>
      No scan jobs yet. <a href="scan_trigger.php">Start a security review</a>.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="jobsTable">
        <thead>
          <tr>
            <th style="width:55px;">#</th>
            <th>Target</th>
            <th>Modules</th>
            <th>Status</th>
            <th>AI Model</th>
            <th>Analyst</th>
            <th>Date</th>
            <th style="width:100px;"></th>
          </tr>
        </thead>
        <tbody id="jobsBody">
          <?php foreach ($jobs as $job):
            $s = $job['status'] ?? 'unknown';
            $sbadge = match($s) {
              'completed' => '<span class="badge" style="background:#dcfce7;color:#16a34a;font-size:.7rem;">Completed</span>',
              'partial'   => '<span class="badge" style="background:#fef3c7;color:#d97706;font-size:.7rem;">Partial</span>',
              'failed'    => '<span class="badge" style="background:#fee2e2;color:#dc2626;font-size:.7rem;">Failed</span>',
              default     => '<span class="badge badge-secondary" style="font-size:.7rem;">'.htmlspecialchars($s).'</span>',
            };
          ?>
          <tr data-status="<?= htmlspecialchars($s) ?>" data-target="<?= htmlspecialchars(strtolower($job['target'])) ?>">
            <td class="text-muted small"><?= (int)$job['id'] ?></td>
            <td><code style="font-size:.78rem;"><?= htmlspecialchars($job['target']) ?></code></td>
            <td>
              <?php foreach (explode(',', $job['scan_types']??'') as $t): ?>
                <span class="badge badge-secondary mr-1" style="font-size:.65rem;"><?= htmlspecialchars(trim($t)) ?></span>
              <?php endforeach; ?>
            </td>
            <td><?= $sbadge ?></td>
            <td><small class="text-muted"><?= htmlspecialchars($job['model']??'—') ?></small></td>
            <td><small><?= htmlspecialchars($job['analyst']??'—') ?></small></td>
            <td><small class="text-muted"><?= htmlspecialchars(substr($job['created_at']??'',0,16)) ?></small></td>
            <td>
              <button class="btn btn-sm btn-outline-primary px-2 py-1 mr-1" onclick="viewJob(<?= (int)$job['id'] ?>)" title="View">
                <i class="fas fa-eye"></i>
              </button>
              <a href="scan_trigger.php?target=<?= urlencode($job['target']) ?>" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Re-scan">
                <i class="fas fa-redo"></i>
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

<!-- ── Detail modal ───────────────────────────────────────────────── -->
<div class="modal fade" id="jobModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:1rem;border:none;box-shadow:0 25px 60px rgba(0,0,0,.2);">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));border-radius:1rem 1rem 0 0;">
        <h5 class="modal-title text-white font-weight-bold">
          <i class="fas fa-shield-alt mr-2"></i>
          Job #<span id="mJobId"></span> &mdash; <span id="mTarget"></span>
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">

        <div class="d-flex mb-3" style="gap:.5rem;flex-wrap:wrap;">
          <span id="mStatus"></span>
          <span id="mModel" class="badge" style="background:#eef2ff;color:#6366f1;font-size:.72rem;"></span>
          <span id="mDate" class="badge badge-secondary" style="font-size:.72rem;"></span>
          <span id="mTypes" style="display:inline;"></span>
        </div>

        <div class="mb-4">
          <div class="d-flex align-items-center mb-2">
            <div style="width:26px;height:26px;border-radius:.4rem;background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));display:inline-flex;align-items:center;justify-content:center;margin-right:.5rem;">
              <i class="fas fa-robot text-white" style="font-size:.7rem;"></i>
            </div>
            <strong style="font-size:.88rem;">AI Triage Analysis</strong>
          </div>
          <div id="mAnalysis"
               style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem 1.25rem;white-space:pre-wrap;font-size:.82rem;line-height:1.65;max-height:380px;overflow-y:auto;">
          </div>
        </div>

        <div>
          <button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-toggle="collapse" data-target="#mRawCollapse">
            <i class="fas fa-terminal mr-1"></i>Raw Scan Output
          </button>
          <div class="collapse" id="mRawCollapse">
            <pre id="mRaw" style="background:#0f172a;color:#4ade80;border-radius:.75rem;padding:1rem;font-size:.72rem;max-height:280px;overflow:auto;"></pre>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid #f1f5f9;">
        <button class="btn btn-sm btn-outline-secondary" onclick="copyModal()">
          <i class="fas fa-copy mr-1"></i>Copy Analysis
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
// ── Filter ────────────────────────────────────────────────────────
function applyFilter() {
  const q  = document.getElementById('searchBox').value.toLowerCase();
  const st = document.getElementById('filterStatus').value;
  document.querySelectorAll('#jobsBody tr').forEach(row => {
    const matchTarget = !q  || row.dataset.target.includes(q);
    const matchStatus = !st || row.dataset.status === st;
    row.style.display = matchTarget && matchStatus ? '' : 'none';
  });
}
document.getElementById('searchBox')?.addEventListener('input', applyFilter);
document.getElementById('filterStatus')?.addEventListener('change', applyFilter);

// ── View job detail ───────────────────────────────────────────────
async function viewJob(id) {
  const resp = await fetch(`scan_jobs.php?detail=${id}`);
  const d = await resp.json();
  if (d.error) { toast(d.error, 'danger'); return; }

  document.getElementById('mJobId').textContent  = d.id;
  document.getElementById('mTarget').textContent = d.target;
  document.getElementById('mAnalysis').textContent = d.analysis || '(no analysis)';
  document.getElementById('mRaw').textContent     = d.raw_output || '(empty)';
  document.getElementById('mModel').textContent   = 'Model: ' + (d.model || '—');
  document.getElementById('mDate').textContent    = (d.created_at || '').slice(0,16);

  const statColors = { completed:'#dcfce7:#16a34a', partial:'#fef3c7:#d97706', failed:'#fee2e2:#dc2626' };
  const [bg,col]   = (statColors[d.status] || '#f1f5f9:#64748b').split(':');
  document.getElementById('mStatus').innerHTML = `<span class="badge" style="background:${bg};color:${col};font-size:.72rem;">${d.status||'unknown'}</span>`;

  const typesHtml = (d.scan_types||'').split(',').map(t =>
    `<span class="badge badge-secondary mr-1" style="font-size:.65rem;">${t.trim()}</span>`
  ).join('');
  document.getElementById('mTypes').innerHTML = typesHtml;

  $('#jobModal').modal('show');
}

function copyModal() {
  navigator.clipboard.writeText(document.getElementById('mAnalysis').textContent)
    .then(() => toast('Analysis copied.', 'info'));
}
</script>
JS;
?>
<?php require_once '../views/partials/footer.php'; ?>
