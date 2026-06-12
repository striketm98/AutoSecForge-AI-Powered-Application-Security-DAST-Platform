<?php
require_once '../src/auth.php';
require_auth();
$page_title = 'Reports';

// Export a single report as text/plain
if (isset($_GET['export']) && ctype_digit($_GET['export'])) {
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM scan_jobs WHERE id=? LIMIT 1');
        $stmt->execute([(int)$_GET['export']]);
        $job  = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($job) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="AutoSecForge_Report_'.(int)$_GET['export'].'.txt"');
            echo "AutoSecForge Pro – Security Review Report\n";
            echo str_repeat('=', 60) . "\n";
            echo "Target     : {$job['target']}\n";
            echo "Scan Types : {$job['scan_types']}\n";
            echo "Status     : {$job['status']}\n";
            echo "Model      : {$job['model']}\n";
            echo "Date       : {$job['created_at']}\n";
            echo str_repeat('=', 60) . "\n\n";
            echo "AI TRIAGE ANALYSIS\n";
            echo str_repeat('-', 60) . "\n";
            echo $job['analysis'] . "\n\n";
            echo "RAW SCAN OUTPUT\n";
            echo str_repeat('-', 60) . "\n";
            echo $job['raw_output'];
            exit;
        }
    } catch (Throwable) {}
    http_response_code(404); exit('Report not found.');
}

$jobs = []; $db_error = null;
try {
    $pdo  = Database::getInstance();
    $jobs = $pdo->query(
        "SELECT j.*, u.full_name analyst FROM scan_jobs j
           LEFT JOIN users u ON u.id=j.triggered_by
          WHERE j.status IN ('completed','partial')
          ORDER BY j.created_at DESC LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $db_error = $e->getMessage(); }
?>
<?php require_once '../views/partials/header.php'; ?>

<div id="pageActions">
  <a href="scan_trigger.php" class="btn btn-asf btn-sm px-3">
    <i class="fas fa-plus mr-1"></i>New Scan
  </a>
</div>

<?php if ($db_error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($db_error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="card-title"><i class="fas fa-file-alt mr-2" style="color:var(--asf-indigo);"></i>Security Review Reports</span>
    <span class="badge" style="background:#eef2ff;color:#6366f1;"><?= count($jobs) ?> report<?= count($jobs)!=1?'s':'' ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($jobs)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-file-circle-xmark fa-2x d-block mb-2 opacity-50"></i>
      No completed reports yet. <a href="scan_trigger.php">Run a security review</a>.
    </div>
    <?php else: ?>
    <div class="row p-3" style="gap:.75rem 0;">
      <?php foreach ($jobs as $job):
        $scolor = $job['status']==='completed' ? '#16a34a' : '#d97706';
        $sbg    = $job['status']==='completed' ? '#dcfce7' : '#fef3c7';
      ?>
      <div class="col-12 col-md-6 col-xl-4 mb-1">
        <div class="card h-100" style="border:1px solid #f1f5f9 !important;">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <code style="font-size:.8rem;color:#1e293b;font-weight:600;"><?= htmlspecialchars($job['target']) ?></code>
              <span class="badge ml-2 flex-shrink-0" style="background:<?=$sbg?>;color:<?=$scolor?>;font-size:.68rem;"><?= ucfirst($job['status']) ?></span>
            </div>
            <div class="mb-2">
              <?php foreach (explode(',', $job['scan_types']??'') as $t): ?>
                <span class="badge badge-secondary mr-1" style="font-size:.62rem;"><?= htmlspecialchars(trim($t)) ?></span>
              <?php endforeach; ?>
            </div>
            <?php if (!empty($job['analysis'])): ?>
            <div class="text-muted small mb-3" style="overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;line-height:1.5;">
              <?= htmlspecialchars(substr($job['analysis'], 0, 200)) ?>…
            </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted"><?= htmlspecialchars(substr($job['created_at']??'',0,16)) ?></small>
              <div>
                <button class="btn btn-sm btn-outline-primary px-2 py-1 mr-1" onclick="previewReport(<?=(int)$job['id']?>)">
                  <i class="fas fa-eye mr-1"></i>View
                </button>
                <a href="report.php?export=<?=(int)$job['id']?>" class="btn btn-sm btn-outline-success px-2 py-1">
                  <i class="fas fa-download mr-1"></i>Export
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Preview modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:1rem;border:none;box-shadow:0 25px 60px rgba(0,0,0,.2);">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));border-radius:1rem 1rem 0 0;">
        <h5 class="modal-title text-white font-weight-bold">
          <i class="fas fa-file-alt mr-2"></i>Report Preview
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <strong id="rTarget" style="font-size:.9rem;"></strong>
            <span id="rDate" class="badge badge-secondary" style="font-size:.72rem;"></span>
          </div>
          <div id="rTypes"></div>
        </div>
        <h6 class="font-weight-bold mb-2"><i class="fas fa-robot mr-1" style="color:var(--asf-indigo);"></i>AI Triage Analysis</h6>
        <div id="rAnalysis" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem 1.25rem;white-space:pre-wrap;font-size:.82rem;line-height:1.65;max-height:420px;overflow-y:auto;"></div>
      </div>
      <div class="modal-footer" style="border-top:1px solid #f1f5f9;">
        <a id="rExportLink" href="#" class="btn btn-sm btn-outline-success"><i class="fas fa-download mr-1"></i>Export .txt</a>
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
async function previewReport(id) {
  const resp = await fetch(`scan_jobs.php?detail=${id}`);
  const d = await resp.json();
  if (d.error) { toast(d.error, 'danger'); return; }

  document.getElementById('rTarget').textContent   = d.target;
  document.getElementById('rDate').textContent     = (d.created_at||'').slice(0,16);
  document.getElementById('rAnalysis').textContent = d.analysis || '(no analysis)';
  document.getElementById('rExportLink').href      = `report.php?export=${d.id}`;
  document.getElementById('rTypes').innerHTML      = (d.scan_types||'').split(',').map(t =>
    `<span class="badge badge-secondary mr-1" style="font-size:.65rem;">${t.trim()}</span>`
  ).join('');

  $('#reportModal').modal('show');
}
</script>
JS;
?>
<?php require_once '../views/partials/footer.php'; ?>
