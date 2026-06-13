<?php
require_once '../src/auth.php';
require_once '../src/helpers.php';
require_auth();

if (!in_array($_SESSION['user_role'] ?? '', ['admin','manager','analyst'])) {
    http_response_code(403); exit('Access denied.');
}

$page_title = 'Code Analysis (SAST)';

$env     = @parse_ini_file('/var/www/html/.env', false, INI_SCANNER_RAW) ?: [];
$MCP_URL = rtrim($env['MCP_URL'] ?? getenv('MCP_URL') ?: 'http://mcp-router:6300', '/');
$SAST_DIR = '/var/www/sast';   // shared volume; scanner sees it as /usr/src

// ── AJAX upload + scan handler ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    set_time_limit(0);

    if (empty($_FILES['src']) || $_FILES['src']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'No source archive uploaded (or it exceeded the size limit).']); exit;
    }
    if (strtolower(pathinfo($_FILES['src']['name'], PATHINFO_EXTENSION)) !== 'zip') {
        echo json_encode(['error' => 'Please upload a .zip of the source tree.']); exit;
    }
    if (!class_exists('ZipArchive')) {
        echo json_encode(['error' => 'PHP zip extension missing on the app image (rebuild required).']); exit;
    }

    // Unique project id / base dir (sanitised → matches mcp SAFE_ID)
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', pathinfo($_FILES['src']['name'], PATHINFO_FILENAME)));
    $base = trim(substr($base, 0, 40), '-') ?: 'project';
    $id   = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    $dest = $SAST_DIR . '/' . $id;

    if (!is_dir($SAST_DIR) && !@mkdir($SAST_DIR, 0775, true)) {
        echo json_encode(['error' => 'SAST upload area is not writable. Ensure the sast-src volume is mounted.']); exit;
    }
    @mkdir($dest, 0775, true);

    // Extract with zip-slip protection
    $zip = new ZipArchive();
    if ($zip->open($_FILES['src']['tmp_name']) !== true) {
        echo json_encode(['error' => 'Could not open the zip archive.']); exit;
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if ($entry === false || str_contains($entry, '..') || str_starts_with($entry, '/')) {
            $zip->close();
            echo json_encode(['error' => 'Unsafe path in archive — aborting.']); exit;
        }
    }
    $zip->extractTo($dest);
    $zip->close();

    // Call the MCP SAST orchestrator
    $ch = curl_init("$MCP_URL/scan/sast");
    curl_setopt_array($ch, [
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode(['project'=>$id, 'base_dir'=>$id]),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>600,
    ]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);

    // Reclaim disk regardless of outcome
    asf_rrmdir($dest);

    if ($err) { echo json_encode(['error'=>"MCP unreachable: $err"]); exit; }
    $result = json_decode($raw, true) ?: ['error'=>'Invalid MCP response.'];

    if (empty($result['error'])) {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'INSERT INTO scan_jobs (target, scan_types, raw_output, analysis, model, triggered_by, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $id, 'sast',
                $result['raw_output'] ?? '', $result['analysis'] ?? '',
                $result['model'] ?? '', $_SESSION['user_id'] ?? null, 'completed',
            ]);
            $job_id = $pdo->lastInsertId();
            $result['job_id'] = $job_id;
            asf_audit('scan.sast', "project=$id job=$job_id");
            if (!empty($result['findings']) && is_array($result['findings'])) {
                $fstmt = $pdo->prepare(
                    'INSERT INTO findings (scan_job_id, title, description, severity, affected_url, remediation)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $allowed = ['critical','high','medium','low','info'];
                foreach ($result['findings'] as $f) {
                    if (empty($f['title'])) continue;
                    $sev = strtolower(trim($f['severity'] ?? 'medium'));
                    if (!in_array($sev, $allowed, true)) $sev = 'medium';
                    try { $fstmt->execute([$job_id, mb_substr($f['title'],0,500), $f['description']??'', $sev,
                        mb_substr($f['affected_url']??'',0,1000), $f['remediation']??'']); } catch (Throwable) {}
                }
            }
        } catch (Throwable $e) { $result['db_warning'] = $e->getMessage(); }
    }
    echo json_encode($result); exit;
}

/** Recursive rmdir helper (cleanup of the extracted source). */
function asf_rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        is_dir($p) ? asf_rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}
?>
<?php require_once '../views/partials/header.php'; ?>

<div id="pageActions">
  <a href="report.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-alt mr-1"></i>Reports</a>
</div>

<div class="row">
  <div class="col-lg-4 mb-4">
    <div class="card">
      <div class="card-header card-header-gradient">
        <span class="card-title text-white"><i class="fas fa-code mr-2"></i>Static Code Analysis</span>
      </div>
      <div class="card-body">
        <form id="sastForm">
          <div class="form-group">
            <label class="font-weight-bold" style="font-size:.82rem;color:#374151;">Source archive <span class="text-danger">*</span></label>
            <div id="dropZone" style="border:2px dashed #c7d2fe;border-radius:.75rem;padding:1.75rem 1rem;text-align:center;cursor:pointer;background:#f8faff;">
              <i class="fas fa-file-archive fa-2x mb-2" style="color:#a5b4fc;"></i>
              <div id="dzText" style="font-size:.82rem;color:#64748b;">Click or drop a <strong>.zip</strong> of your source tree</div>
              <input type="file" id="srcFile" name="src" accept=".zip" style="display:none;">
            </div>
            <small class="text-muted">Analysed by SonarQube. Max ~300&nbsp;MB.</small>
          </div>
          <button type="submit" class="btn btn-asf btn-block py-3 mt-2" id="btnSast">
            <i class="fas fa-magnifying-glass-chart mr-2"></i>Analyse Code
          </button>
        </form>
      </div>
    </div>
    <div class="card mt-3" style="background:#f8faff;border:1px solid #e0e7ff !important;">
      <div class="card-body py-3" style="font-size:.78rem;color:#475569;">
        <i class="fas fa-info-circle mr-1" style="color:var(--asf-indigo);"></i>
        Requires a <strong>SonarQube token</strong> (set <code>SONAR_TOKEN</code> in <code>.env</code>).
        SonarQube scans the code for vulnerabilities, bugs &amp; code smells; results feed the AI triage &amp; report pipeline.
      </div>
    </div>
  </div>

  <div class="col-lg-8 mb-4">
    <div id="sScanning" class="card d-none mb-3" style="border:2px solid #e0e7ff !important;">
      <div class="card-body py-4 text-center">
        <div class="spinner-border" style="color:var(--asf-indigo);width:3rem;height:3rem;"></div>
        <div class="mt-3 font-weight-600" id="sProgress" style="color:#1e293b;">Uploading source…</div>
        <div class="progress mt-3" style="height:6px;border-radius:99px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%;background:linear-gradient(90deg,var(--asf-indigo),var(--asf-violet));"></div>
        </div>
        <small class="text-muted mt-2 d-block">Scanning &amp; waiting for SonarQube analysis…</small>
      </div>
    </div>

    <div id="sError" class="card d-none mb-3" style="border:2px solid #fee2e2 !important;">
      <div class="card-body d-flex align-items-center" style="color:#dc2626;">
        <i class="fas fa-times-circle mr-3 fa-lg"></i>
        <div><div class="font-weight-bold">Analysis Failed</div><div style="font-size:.83rem;" id="sErrorMsg"></div></div>
      </div>
    </div>

    <div id="sResult" class="card d-none">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title"><i class="fas fa-code mr-2" style="color:#16a34a;"></i><span id="sTarget"></span></span>
        <span class="badge" id="sModel" style="background:#eef2ff;color:#6366f1;font-size:.72rem;"></span>
      </div>
      <div class="card-body">
        <div id="sAnalysis" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem 1.25rem;white-space:pre-wrap;font-size:.83rem;line-height:1.65;max-height:380px;overflow-y:auto;"></div>
        <hr>
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted">Job ID: <code id="sJobId">—</code></small>
          <a id="sReport" href="#" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf mr-1"></i>Export Report</a>
        </div>
      </div>
    </div>

    <div id="sPlaceholder" class="card" style="border:2px dashed #e2e8f0 !important;background:transparent;">
      <div class="card-body text-center py-5">
        <i class="fas fa-code fa-2x mb-2" style="color:#c7d2fe;"></i>
        <h6 style="color:#1e293b;font-weight:700;">No code analysed yet</h6>
        <p class="text-muted mb-0" style="font-size:.83rem;">Upload a source .zip to begin static analysis.</p>
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
const dz = document.getElementById('dropZone');
const fi = document.getElementById('srcFile');
dz.addEventListener('click', () => fi.click());
['dragover','dragenter'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.style.background='#eef2ff'; }));
['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.style.background='#f8faff'; }));
dz.addEventListener('drop', e => { if (e.dataTransfer.files.length) { fi.files = e.dataTransfer.files; showName(); } });
fi.addEventListener('change', showName);
function showName(){ if (fi.files.length) document.getElementById('dzText').innerHTML = '<strong>' + fi.files[0].name + '</strong>'; }

document.getElementById('sastForm').addEventListener('submit', async function(e){
  e.preventDefault();
  if (!fi.files.length) { toast('Choose a source .zip first.', 'warning'); return; }

  document.getElementById('btnSast').disabled = true;
  document.getElementById('sScanning').classList.remove('d-none');
  document.getElementById('sResult').classList.add('d-none');
  document.getElementById('sError').classList.add('d-none');
  document.getElementById('sPlaceholder').classList.add('d-none');

  const steps = ['Uploading source…','Running sonar-scanner…','Waiting for SonarQube analysis…','Triaging results…'];
  let i = 0; const t = setInterval(() => { i = Math.min(i+1, steps.length-1); document.getElementById('sProgress').textContent = steps[i]; }, 20000);

  const fd = new FormData();
  fd.append('src', fi.files[0]);

  try {
    const resp = await fetch('sast.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd });
    const d = await resp.json();
    clearInterval(t);
    document.getElementById('sScanning').classList.add('d-none');
    if (d.error) {
      document.getElementById('sError').classList.remove('d-none');
      document.getElementById('sErrorMsg').textContent = d.error;
      toast('Analysis failed: ' + d.error, 'danger');
    } else {
      document.getElementById('sTarget').textContent   = d.target || 'project';
      document.getElementById('sModel').textContent    = 'Model: ' + (d.model || 'unknown');
      document.getElementById('sAnalysis').textContent = d.analysis || '(no analysis)';
      document.getElementById('sJobId').textContent    = d.job_id || '—';
      document.getElementById('sReport').href          = d.job_id ? 'report.php?export=' + d.job_id + '&format=pdf' : '#';
      document.getElementById('sResult').classList.remove('d-none');
      toast('Code analysis completed!', 'success');
    }
  } catch (err) {
    clearInterval(t);
    document.getElementById('sScanning').classList.add('d-none');
    document.getElementById('sError').classList.remove('d-none');
    document.getElementById('sErrorMsg').textContent = err.message;
  } finally {
    document.getElementById('btnSast').disabled = false;
  }
});
</script>
JS;
?>
<?php require_once '../views/partials/footer.php'; ?>
