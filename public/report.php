<?php
require_once '../src/auth.php';
require_auth();
require_once '../src/report_render.php';
$page_title = 'Reports';

// ── Export a single report: ?export=ID&format=pdf|doc|html|txt ─────
if (isset($_GET['export']) && ctype_digit($_GET['export'])) {
    $id     = (int)$_GET['export'];
    $format = strtolower($_GET['format'] ?? 'pdf');
    if (!in_array($format, ['pdf','doc','html','txt'], true)) $format = 'pdf';
    try {
        $pdo = Database::getInstance();
        if (!asf_can_view_report($pdo, $id)) { http_response_code(403); exit('Access denied.'); }
        $job = asf_get_report_data($pdo, $id);
        if (!$job) { http_response_code(404); exit('Report not found.'); }

        $fnameBase = 'AutoSecForge_Report_ASF-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);

        // ── Plain text (legacy/portable) ──────────────────────────
        if ($format === 'txt') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fnameBase . '.txt"');
            echo "AutoSecForge Pro – Security Review Report\n";
            echo str_repeat('=', 60) . "\n";
            echo "Target     : {$job['target']}\n";
            echo "Scan Types : {$job['scan_types']}\n";
            echo "Status     : {$job['status']}\n";
            echo "Model      : {$job['model']}\n";
            echo "Date       : {$job['created_at']}\n";
            echo str_repeat('=', 60) . "\n\n";
            foreach (($job['findings'] ?? []) as $i => $f) {
                echo sprintf("[%s] %s\n", strtoupper($f['severity']), $f['title']);
                if (!empty($f['remediation'])) echo "  Fix: {$f['remediation']}\n";
            }
            echo "\nAI TRIAGE ANALYSIS\n" . str_repeat('-', 60) . "\n";
            echo $job['analysis'] . "\n\nRAW SCAN OUTPUT\n" . str_repeat('-', 60) . "\n";
            echo $job['raw_output'];
            exit;
        }

        // ── Word (.doc — HTML-based, opens natively in MS Word) ───
        if ($format === 'doc') {
            header('Content-Type: application/msword; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fnameBase . '.doc"');
            echo asf_render_report_html($job, ['for_word' => true]);
            exit;
        }

        $html = asf_render_report_html($job);

        // ── In-browser HTML preview / print-to-PDF fallback ───────
        if ($format === 'html') {
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        }

        // ── PDF via wkhtmltopdf (real file) ───────────────────────
        $bin = trim((string)@shell_exec('command -v wkhtmltopdf 2>/dev/null'));
        if ($bin === '') {
            // Renderer missing — fall back to a print-optimised HTML view.
            header('Content-Type: text/html; charset=utf-8');
            echo str_replace('</body>',
                '<script>window.onload=function(){window.print();};</script></body>', $html);
            exit;
        }
        // Debian's wkhtmltopdf uses unpatched Qt and needs an X server → xvfb-run.
        $xvfb = trim((string)@shell_exec('command -v xvfb-run 2>/dev/null'));
        $cmd  = ($xvfb !== '' ? escapeshellarg($xvfb) . ' -a ' : '')
             . escapeshellarg($bin)
             . ' --quiet --enable-local-file-access --print-media-type'
             . ' --encoding utf-8 --page-size A4'
             . ' --margin-top 14mm --margin-bottom 14mm --margin-left 12mm --margin-right 12mm'
             . ' - -';   // read HTML from stdin, write PDF to stdout
        $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (is_resource($proc)) {
            fwrite($pipes[0], $html); fclose($pipes[0]);
            $pdf = stream_get_contents($pipes[1]); fclose($pipes[1]);
            fclose($pipes[2]); proc_close($proc);
            if ($pdf !== '' && substr($pdf, 0, 4) === '%PDF') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $fnameBase . '.pdf"');
                header('Content-Length: ' . strlen($pdf));
                echo $pdf; exit;
            }
        }
        // PDF generation failed — print-to-PDF fallback.
        header('Content-Type: text/html; charset=utf-8');
        echo str_replace('</body>',
            '<script>window.onload=function(){window.print();};</script></body>', $html);
        exit;
    } catch (Throwable $e) {
        http_response_code(500); exit('Export failed: ' . htmlspecialchars($e->getMessage()));
    }
}

$jobs = []; $db_error = null; $clients = []; $is_staff_lead = false;
try {
    $pdo  = Database::getInstance();
    $is_staff_lead = in_array($_SESSION['user_role'] ?? '', ['admin','manager','auditor','executive'], true);
    // Staff leads may filter by client; client/analyst are auto-scoped to their own.
    $clientFilter = $is_staff_lead ? asf_valid_client_id($_GET['client'] ?? null) : null;
    if ($is_staff_lead) $clients = asf_clients($pdo);
    [$scopeSql, $scopeParams] = asf_report_scope($clientFilter);

    $stmt = $pdo->prepare(
        "SELECT j.*, u.full_name analyst, c.full_name client_name
           FROM scan_jobs j
           LEFT JOIN users u ON u.id = j.triggered_by
           LEFT JOIN users c ON c.id = j.client_id
          WHERE j.status IN ('completed','partial') AND $scopeSql
          ORDER BY j.created_at DESC LIMIT 200"
    );
    $stmt->execute($scopeParams);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $db_error = $e->getMessage(); }
?>
<?php require_once '../views/partials/header.php'; ?>

<div id="pageActions" class="d-flex align-items-center" style="gap:.5rem;">
  <?php if ($is_staff_lead && $clients): ?>
  <form method="get" class="d-flex align-items-center" style="gap:.35rem;">
    <select name="client" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
      <option value="">All clients</option>
      <?php $cf = (int)($_GET['client'] ?? 0); foreach ($clients as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $cf === (int)$c['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['full_name']) ?><?= $c['company'] ? ' — ' . htmlspecialchars($c['company']) : '' ?>
      </option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
  <?php if (($_SESSION['user_role'] ?? '') !== 'client'): ?>
  <a href="scan_trigger.php" class="btn btn-asf btn-sm px-3">
    <i class="fas fa-plus mr-1"></i>New Scan
  </a>
  <?php endif; ?>
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
            <?php if (!empty($job['client_name'])): ?>
            <div class="mb-1"><span class="badge" style="background:#f0fdf4;color:#16a34a;font-size:.62rem;"><i class="fas fa-building mr-1"></i><?= htmlspecialchars($job['client_name']) ?></span></div>
            <?php endif; ?>
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
                <div class="btn-group">
                  <button type="button" class="btn btn-sm btn-outline-success px-2 py-1 dropdown-toggle" data-toggle="dropdown">
                    <i class="fas fa-download mr-1"></i>Export
                  </button>
                  <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="report.php?export=<?=(int)$job['id']?>&format=pdf"><i class="fas fa-file-pdf fa-fw mr-2 text-danger"></i>PDF document</a>
                    <a class="dropdown-item" href="report.php?export=<?=(int)$job['id']?>&format=doc"><i class="fas fa-file-word fa-fw mr-2 text-primary"></i>Word (.doc)</a>
                    <a class="dropdown-item" href="report.php?export=<?=(int)$job['id']?>&format=html" target="_blank"><i class="fas fa-file-code fa-fw mr-2 text-info"></i>HTML preview</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="report.php?export=<?=(int)$job['id']?>&format=txt"><i class="fas fa-file-alt fa-fw mr-2 text-muted"></i>Plain text</a>
                  </div>
                </div>
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
        <a id="rPdfLink"  href="#" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        <a id="rDocLink"  href="#" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-word mr-1"></i>Word</a>
        <a id="rTxtLink"  href="#" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-alt mr-1"></i>Text</a>
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
  document.getElementById('rPdfLink').href = `report.php?export=${d.id}&format=pdf`;
  document.getElementById('rDocLink').href = `report.php?export=${d.id}&format=doc`;
  document.getElementById('rTxtLink').href = `report.php?export=${d.id}&format=txt`;
  document.getElementById('rTypes').innerHTML      = (d.scan_types||'').split(',').map(t =>
    `<span class="badge badge-secondary mr-1" style="font-size:.65rem;">${t.trim()}</span>`
  ).join('');

  $('#reportModal').modal('show');
}
</script>
JS;
?>
<?php require_once '../views/partials/footer.php'; ?>
