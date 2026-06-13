<?php
require_once '../src/auth.php';
require_once '../src/helpers.php';
require_auth();

if (!in_array($_SESSION['user_role'] ?? '', ['admin','manager','analyst'])) {
    http_response_code(403); exit('Access denied.');
}

$page_title = 'Mobile App Scan';

$env       = @parse_ini_file('/var/www/html/.env', false, INI_SCANNER_RAW) ?: [];
$MOBSF_URL = rtrim($env['MOBSF_URL'] ?? getenv('MOBSF_URL') ?: 'http://mobsf:8000', '/');
$MOBSF_KEY = $env['MOBSF_API_KEY'] ?? getenv('MOBSF_API_KEY') ?: '';
$AI_URL    = rtrim($env['AI_AGENT_URL'] ?? getenv('AI_AGENT_URL') ?: 'http://ai-agent:6400', '/');

/** Small JSON/form POST helper for the MobSF REST API. */
function mobsf_post(string $url, array $fields, string $key, int $timeout = 600, bool $raw = false) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $key],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($raw) return [$body, $code, $err];
    return [json_decode($body, true), $code, $err];
}

// ── Native MobSF PDF proxy: mobsf.php?pdf=<hash> ───────────────────
if (isset($_GET['pdf']) && preg_match('/^[a-f0-9]{32,64}$/i', $_GET['pdf'])) {
    [$body, $code, $err] = mobsf_post("$MOBSF_URL/api/v1/download_pdf", ['hash' => $_GET['pdf']], $MOBSF_KEY, 120, true);
    if ($code === 200 && $body && substr($body, 0, 4) === '%PDF') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="MobSF_Report_' . substr($_GET['pdf'],0,12) . '.pdf"');
        echo $body; exit;
    }
    http_response_code(502); exit('MobSF PDF unavailable (' . htmlspecialchars((string)$code) . ').');
}

// ── AJAX upload + scan handler ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    set_time_limit(0);

    if ($MOBSF_KEY === '') {
        echo json_encode(['error' => 'MOBSF_API_KEY is not set. Add it to .env (same value as the mobsf service) and restart.']); exit;
    }
    if (empty($_FILES['app']) || $_FILES['app']['error'] !== UPLOAD_ERR_OK) {
        $codeMap = [UPLOAD_ERR_INI_SIZE=>'File exceeds the server upload limit.', UPLOAD_ERR_FORM_SIZE=>'File too large.', UPLOAD_ERR_NO_FILE=>'No file selected.'];
        echo json_encode(['error' => $codeMap[$_FILES['app']['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload failed.']); exit;
    }

    $name = $_FILES['app']['name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['apk','xapk','ipa','appx','zip'], true)) {
        echo json_encode(['error' => 'Unsupported file type. Allowed: apk, xapk, ipa, appx, zip.']); exit;
    }

    // 1) Upload to MobSF
    $cfile = new CURLFile($_FILES['app']['tmp_name'], mime_content_type($_FILES['app']['tmp_name']) ?: 'application/octet-stream', $name);
    [$up, $code, $err] = mobsf_post("$MOBSF_URL/api/v1/upload", ['file' => $cfile], $MOBSF_KEY, 300);
    if ($err)                 { echo json_encode(['error' => "MobSF unreachable: $err"]); exit; }
    if ($code === 401)        { echo json_encode(['error' => 'MobSF rejected the API key (401). Check MOBSF_API_KEY matches the mobsf service.']); exit; }
    if (empty($up['hash']))   { echo json_encode(['error' => 'MobSF upload failed (HTTP ' . $code . ').']); exit; }
    $hash = $up['hash'];

    // 2) Trigger the static scan (synchronous; can take a couple of minutes)
    [$scan, $code, $err] = mobsf_post("$MOBSF_URL/api/v1/scan", ['hash' => $hash], $MOBSF_KEY, 600);
    if ($err) { echo json_encode(['error' => "MobSF scan failed: $err"]); exit; }

    // 3) Scorecard → clean severity-tagged items
    [$card, $code, $err] = mobsf_post("$MOBSF_URL/api/v1/scorecard", ['hash' => $hash], $MOBSF_KEY, 120);
    $card = is_array($card) ? $card : [];

    $appName = $card['app_name'] ?? ($scan['app_name'] ?? $name);
    $score   = $card['security_score'] ?? ($scan['security_score'] ?? null);

    $sevMap  = ['high' => 'high', 'warning' => 'medium', 'info' => 'info', 'hotspot' => 'low'];
    $findings = [];
    foreach ($sevMap as $bucket => $sev) {
        foreach (($card[$bucket] ?? []) as $item) {
            $findings[] = [
                'title'        => mb_substr($item['title'] ?? $item['description'] ?? 'Mobile issue', 0, 500),
                'severity'     => $sev,
                'description'  => trim(($item['description'] ?? '') . (isset($item['section']) ? "\n[Section: {$item['section']}]" : '')),
                'remediation'  => $item['remediation'] ?? '',
                'affected_url' => $appName,
            ];
        }
    }

    // 4) Build a compact summary for the AI narrative
    $summary  = "MobSF static analysis of: $appName ($name)\n";
    $summary .= "Security score: " . ($score ?? 'n/a') . "/100\n";
    $summary .= 'Issue counts — high: ' . count($card['high'] ?? []) . ', warning: ' . count($card['warning'] ?? [])
              . ', info: ' . count($card['info'] ?? []) . ', hotspot: ' . count($card['hotspot'] ?? []) . "\n\n";
    foreach ($findings as $f) {
        $summary .= "[" . strtoupper($f['severity']) . "] " . $f['title'] . "\n";
    }
    $summary = mb_substr($summary, 0, 11000);

    // 5) AI triage narrative (best effort)
    $analysis = ''; $model = 'unknown';
    $ch = curl_init("$AI_URL/v1/security-review");
    curl_setopt_array($ch, [
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode(['target'=>$appName, 'scan_type'=>'mobile', 'raw_output'=>$summary]),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>200,
    ]);
    $aiRaw = curl_exec($ch); curl_close($ch);
    if ($aiRaw && ($ai = json_decode($aiRaw, true))) {
        $analysis = $ai['analysis'] ?? '';
        $model    = $ai['model'] ?? 'unknown';
    }
    if ($analysis === '') $analysis = "MobSF scored $appName at " . ($score ?? 'n/a') . "/100 with "
        . count($findings) . " flagged items. (AI narrative unavailable — Ollama may be offline.)";

    // 6) Persist as a scan_job + findings
    $result = [
        'target'     => $appName,
        'hash'       => $hash,
        'score'      => $score,
        'analysis'   => $analysis,
        'model'      => $model,
        'findings'   => $findings,
        'raw_output' => $summary,
        'ok'         => true,
        'timestamp'  => gmdate('c'),
    ];
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO scan_jobs (target, scan_types, raw_output, analysis, model, triggered_by, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$appName, 'mobile', $summary, $analysis, $model, $_SESSION['user_id'] ?? null, 'completed']);
        $job_id = $pdo->lastInsertId();
        $result['job_id'] = $job_id;
        asf_audit('scan.mobile', "app=$appName job=$job_id");

        $fstmt = $pdo->prepare(
            'INSERT INTO findings (scan_job_id, title, description, severity, affected_url, remediation)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($findings as $f) {
            try { $fstmt->execute([$job_id, $f['title'], $f['description'], $f['severity'], mb_substr($f['affected_url'],0,1000), $f['remediation']]); }
            catch (Throwable) {}
        }
    } catch (Throwable $e) { $result['db_warning'] = $e->getMessage(); }

    echo json_encode($result); exit;
}
?>
<?php require_once '../views/partials/header.php'; ?>

<div id="pageActions">
  <a href="report.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-alt mr-1"></i>Reports</a>
</div>

<div class="row">
  <!-- Upload panel -->
  <div class="col-lg-4 mb-4">
    <div class="card">
      <div class="card-header card-header-gradient">
        <span class="card-title text-white"><i class="fas fa-mobile-alt mr-2"></i>Mobile App Analysis</span>
      </div>
      <div class="card-body">
        <form id="mobsfForm">
          <div class="form-group">
            <label class="font-weight-bold" style="font-size:.82rem;color:#374151;">Application file <span class="text-danger">*</span></label>
            <div id="dropZone" style="border:2px dashed #c7d2fe;border-radius:.75rem;padding:1.75rem 1rem;text-align:center;cursor:pointer;transition:all .15s;background:#f8faff;">
              <i class="fas fa-cloud-upload-alt fa-2x mb-2" style="color:#a5b4fc;"></i>
              <div id="dzText" style="font-size:.82rem;color:#64748b;">Click to choose or drop an<br><strong>APK / XAPK / IPA / APPX</strong> file</div>
              <input type="file" id="appFile" name="app" accept=".apk,.xapk,.ipa,.appx,.zip" style="display:none;">
            </div>
            <small class="text-muted">Static analysis via MobSF. Max ~300&nbsp;MB.</small>
          </div>
          <button type="submit" class="btn btn-asf btn-block py-3 mt-2" id="btnMobsf">
            <i class="fas fa-shield-virus mr-2"></i>Analyse App
          </button>
        </form>
      </div>
    </div>
    <div class="card mt-3" style="background:#f8faff;border:1px solid #e0e7ff !important;">
      <div class="card-body py-3" style="font-size:.78rem;color:#475569;">
        <i class="fas fa-info-circle mr-1" style="color:var(--asf-indigo);"></i>
        MobSF decompiles the app, runs static checks (permissions, secrets, insecure APIs,
        manifest issues), scores it, and the result feeds the AI triage &amp; report pipeline.
      </div>
    </div>
  </div>

  <!-- Result panel -->
  <div class="col-lg-8 mb-4">
    <div id="mScanning" class="card d-none mb-3" style="border:2px solid #e0e7ff !important;">
      <div class="card-body py-4 text-center">
        <div class="spinner-border" style="color:var(--asf-indigo);width:3rem;height:3rem;"></div>
        <div class="mt-3 font-weight-600" id="mProgress" style="color:#1e293b;">Uploading to MobSF…</div>
        <div class="progress mt-3" style="height:6px;border-radius:99px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%;background:linear-gradient(90deg,var(--asf-indigo),var(--asf-violet));"></div>
        </div>
        <small class="text-muted mt-2 d-block">Decompiling &amp; analysing — this can take a few minutes.</small>
      </div>
    </div>

    <div id="mError" class="card d-none mb-3" style="border:2px solid #fee2e2 !important;">
      <div class="card-body d-flex align-items-center" style="color:#dc2626;">
        <i class="fas fa-times-circle mr-3 fa-lg"></i>
        <div><div class="font-weight-bold">Scan Failed</div><div style="font-size:.83rem;" id="mErrorMsg"></div></div>
      </div>
    </div>

    <div id="mResult" class="card d-none">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title"><i class="fas fa-mobile-alt mr-2" style="color:#16a34a;"></i><span id="mTarget"></span></span>
        <div>
          <span class="badge mr-1" id="mScore" style="background:#eef2ff;color:#6366f1;font-size:.72rem;"></span>
          <span class="badge" style="background:#dcfce7;color:#16a34a;font-size:.7rem;">Completed</span>
        </div>
      </div>
      <div class="card-body">
        <div id="mAnalysis" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem 1.25rem;white-space:pre-wrap;font-size:.83rem;line-height:1.65;max-height:380px;overflow-y:auto;"></div>
        <hr>
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
          <small class="text-muted">Job ID: <code id="mJobId">—</code></small>
          <div>
            <a id="mPdf" href="#" class="btn btn-sm btn-outline-danger mr-1"><i class="fas fa-file-pdf mr-1"></i>MobSF PDF</a>
            <a id="mReport" href="#" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-alt mr-1"></i>ASF Report</a>
          </div>
        </div>
      </div>
    </div>

    <div id="mPlaceholder" class="card" style="border:2px dashed #e2e8f0 !important;background:transparent;">
      <div class="card-body text-center py-5">
        <i class="fas fa-mobile-alt fa-2x mb-2" style="color:#c7d2fe;"></i>
        <h6 style="color:#1e293b;font-weight:700;">No app analysed yet</h6>
        <p class="text-muted mb-0" style="font-size:.83rem;">Upload an APK/IPA to begin static analysis.</p>
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
const dz = document.getElementById('dropZone');
const fi = document.getElementById('appFile');
dz.addEventListener('click', () => fi.click());
['dragover','dragenter'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.style.background='#eef2ff'; }));
['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.style.background='#f8faff'; }));
dz.addEventListener('drop', e => { if (e.dataTransfer.files.length) { fi.files = e.dataTransfer.files; showName(); } });
fi.addEventListener('change', showName);
function showName(){ if (fi.files.length) document.getElementById('dzText').innerHTML = '<strong>' + fi.files[0].name + '</strong>'; }

document.getElementById('mobsfForm').addEventListener('submit', async function(e){
  e.preventDefault();
  if (!fi.files.length) { toast('Choose an app file first.', 'warning'); return; }

  document.getElementById('btnMobsf').disabled = true;
  document.getElementById('mScanning').classList.remove('d-none');
  document.getElementById('mResult').classList.add('d-none');
  document.getElementById('mError').classList.add('d-none');
  document.getElementById('mPlaceholder').classList.add('d-none');

  const steps = ['Uploading to MobSF…','Decompiling app…','Running static checks…','Scoring & triaging…'];
  let i = 0; const t = setInterval(() => { i = Math.min(i+1, steps.length-1); document.getElementById('mProgress').textContent = steps[i]; }, 20000);

  const fd = new FormData();
  fd.append('app', fi.files[0]);

  try {
    const resp = await fetch('mobsf.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd });
    const d = await resp.json();
    clearInterval(t);
    document.getElementById('mScanning').classList.add('d-none');
    if (d.error) {
      document.getElementById('mError').classList.remove('d-none');
      document.getElementById('mErrorMsg').textContent = d.error;
      toast('Scan failed: ' + d.error, 'danger');
    } else {
      document.getElementById('mTarget').textContent   = d.target || fi.files[0].name;
      document.getElementById('mScore').textContent    = 'Score: ' + (d.score ?? 'n/a') + '/100';
      document.getElementById('mAnalysis').textContent = d.analysis || '(no analysis)';
      document.getElementById('mJobId').textContent    = d.job_id || '—';
      document.getElementById('mPdf').href             = 'mobsf.php?pdf=' + d.hash;
      document.getElementById('mReport').href          = d.job_id ? 'report.php?export=' + d.job_id + '&format=pdf' : '#';
      document.getElementById('mResult').classList.remove('d-none');
      toast('Mobile analysis completed!', 'success');
    }
  } catch (err) {
    clearInterval(t);
    document.getElementById('mScanning').classList.add('d-none');
    document.getElementById('mError').classList.remove('d-none');
    document.getElementById('mErrorMsg').textContent = err.message;
  } finally {
    document.getElementById('btnMobsf').disabled = false;
  }
});
</script>
JS;
?>
<?php require_once '../views/partials/footer.php'; ?>
