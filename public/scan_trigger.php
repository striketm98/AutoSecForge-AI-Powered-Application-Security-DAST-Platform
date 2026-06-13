<?php
require_once '../src/auth.php';
require_auth();

if (!in_array($_SESSION['user_role'] ?? '', ['admin','manager','analyst'])) {
    http_response_code(403); exit('Access denied.');
}

$page_title = 'New Security Review';

// ── AJAX POST handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $target     = trim($_POST['target'] ?? '');
    $scan_types = array_values(array_filter((array)($_POST['scan_types'] ?? ['network'])));

    if (!$target) { echo json_encode(['error'=>'Target is required.']); exit; }

    // SSRF guard
    $host = parse_url(strpos($target,'http')===0 ? $target : "http://$target", PHP_URL_HOST) ?: $target;
    foreach (['127.','10.','192.168.','172.16.','172.17.','172.18.','172.19.','172.2','172.3','169.254.','localhost','::1'] as $p) {
        if (str_starts_with($host,$p) || $host==='localhost') {
            echo json_encode(['error'=>'Private/internal targets are not permitted.']); exit;
        }
    }

    $env     = @parse_ini_file('/var/www/html/.env', false, INI_SCANNER_RAW) ?: [];
    $mcp_url = rtrim($env['MCP_URL'] ?? 'http://mcp-hackstrike:6300', '/');

    $payload = json_encode(['target'=>$target, 'scan_types'=>$scan_types]);
    $ch = curl_init("$mcp_url/scan/security-review");
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>300,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) { echo json_encode(['error'=>"MCP unreachable: $err"]); exit; }

    $result = json_decode($raw, true) ?? ['error'=>'Invalid MCP response.'];

    if (empty($result['error'])) {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare(
                'INSERT INTO scan_jobs (target, scan_types, raw_output, analysis, model, triggered_by, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $target,
                implode(',', $scan_types),
                $result['raw_output'] ?? '',
                $result['analysis']   ?? '',
                $result['model']      ?? '',
                $_SESSION['user_id']  ?? null,
                $result['ok'] ? 'completed' : 'partial',
            ]);
            $job_id = $pdo->lastInsertId();
            $result['job_id'] = $job_id;

            // Persist structured findings (from the AI agent) for pro reports.
            if (!empty($result['findings']) && is_array($result['findings'])) {
                $fstmt = $pdo->prepare(
                    'INSERT INTO findings
                        (scan_job_id, title, description, severity, cvss_score,
                         cwe_id, cve_id, affected_url, remediation)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $allowed_sev = ['critical','high','medium','low','info'];
                foreach ($result['findings'] as $f) {
                    if (empty($f['title'])) continue;
                    $sev = strtolower(trim($f['severity'] ?? 'medium'));
                    if (!in_array($sev, $allowed_sev, true)) $sev = 'medium';
                    $cvss = isset($f['cvss_score']) && is_numeric($f['cvss_score'])
                          ? max(0, min(10, (float)$f['cvss_score'])) : null;
                    try {
                        $fstmt->execute([
                            $job_id,
                            mb_substr($f['title'], 0, 500),
                            $f['description'] ?? '',
                            $sev,
                            $cvss,
                            mb_substr($f['cwe_id'] ?? '', 0, 20),
                            mb_substr($f['cve_id'] ?? '', 0, 30),
                            mb_substr($f['affected_url'] ?? '', 0, 1000),
                            $f['remediation'] ?? '',
                        ]);
                    } catch (Throwable) { /* skip a bad finding, keep the rest */ }
                }
            }
        } catch (Throwable $e) {
            $result['db_warning'] = $e->getMessage();
        }
    }

    echo json_encode($result);
    exit;
}
?>
<?php require_once '../views/partials/header.php'; ?>

<div id="pageActions">
  <a href="scan_jobs.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-history mr-1"></i>History
  </a>
</div>

<div class="row">

  <!-- ── Left: config panel ─────────────────────────────────────── -->
  <div class="col-lg-4 mb-4">

    <div class="card">
      <div class="card-header card-header-gradient">
        <span class="card-title text-white"><i class="fas fa-sliders-h mr-2"></i>Scan Configuration</span>
      </div>
      <div class="card-body">
        <form id="scanForm">

          <div class="form-group">
            <label class="font-weight-bold" style="font-size:.82rem;color:#374151;">
              Target <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text bg-transparent border-right-0">
                  <i class="fas fa-globe text-muted"></i>
                </span>
              </div>
              <input type="text" class="form-control border-left-0" id="target" name="target"
                     placeholder="example.com or 203.0.113.1"
                     autocomplete="off" required
                     style="border-radius:0 .5rem .5rem 0;">
            </div>
            <small class="text-muted">Public hosts &amp; IPs only. Private ranges are blocked.</small>
          </div>

          <div class="form-group">
            <label class="font-weight-bold" style="font-size:.82rem;color:#374151;">Scan Modules</label>

            <div class="scan-module-option" data-val="network">
              <div class="d-flex align-items-center p-3 rounded mb-2 border" style="cursor:pointer;transition:all .15s;" id="opt_network">
                <div style="width:36px;height:36px;border-radius:.5rem;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin-right:.75rem;flex-shrink:0;">
                  <i class="fas fa-network-wired" style="color:#6366f1;"></i>
                </div>
                <div class="flex-grow-1">
                  <div style="font-size:.82rem;font-weight:600;color:#1e293b;">Network Scan</div>
                  <div style="font-size:.72rem;color:#64748b;">nmap -sV open ports &amp; services</div>
                </div>
                <input type="checkbox" name="scan_types[]" value="network" checked class="ml-2" style="width:16px;height:16px;accent-color:#6366f1;">
              </div>
            </div>

            <div class="scan-module-option" data-val="dast">
              <div class="d-flex align-items-center p-3 rounded mb-2 border" style="cursor:pointer;transition:all .15s;" id="opt_dast">
                <div style="width:36px;height:36px;border-radius:.5rem;background:#eff6ff;display:flex;align-items:center;justify-content:center;margin-right:.75rem;flex-shrink:0;">
                  <i class="fas fa-spider" style="color:#3b82f6;"></i>
                </div>
                <div class="flex-grow-1">
                  <div style="font-size:.82rem;font-weight:600;color:#1e293b;">DAST Scan</div>
                  <div style="font-size:.72rem;color:#64748b;">nikto web vulnerability scan</div>
                </div>
                <input type="checkbox" name="scan_types[]" value="dast" class="ml-2" style="width:16px;height:16px;accent-color:#3b82f6;">
              </div>
            </div>

            <div class="scan-module-option" data-val="sqli">
              <div class="d-flex align-items-center p-3 rounded mb-2 border" style="cursor:pointer;transition:all .15s;" id="opt_sqli">
                <div style="width:36px;height:36px;border-radius:.5rem;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin-right:.75rem;flex-shrink:0;">
                  <i class="fas fa-database" style="color:#ef4444;"></i>
                </div>
                <div class="flex-grow-1">
                  <div style="font-size:.82rem;font-weight:600;color:#1e293b;">SQL Injection</div>
                  <div style="font-size:.72rem;color:#64748b;">sqlmap automated SQLi detection</div>
                </div>
                <input type="checkbox" name="scan_types[]" value="sqli" class="ml-2" style="width:16px;height:16px;accent-color:#ef4444;">
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-asf btn-block py-3 mt-2" id="btnScan">
            <i class="fas fa-play mr-2"></i>Run Security Review
          </button>
        </form>
      </div>
    </div>

    <!-- Info card -->
    <div class="card mt-3" style="background:#f8faff;border:1px solid #e0e7ff !important;">
      <div class="card-body py-3">
        <div class="d-flex align-items-start">
          <i class="fas fa-robot mr-2 mt-1" style="color:var(--asf-indigo);font-size:1.1rem;"></i>
          <div style="font-size:.78rem;color:#475569;">
            <strong style="color:#1e293b;">Ollama AI Triage</strong><br>
            Results are analysed by your local LLM.
            Pull a model first if not done:<br>
            <code style="font-size:.72rem;">docker exec ollama ollama pull llama3</code>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Right: results panel ───────────────────────────────────── -->
  <div class="col-lg-8 mb-4">

    <!-- Scanning progress -->
    <div id="scanProgress" class="card d-none mb-3" style="border:2px solid #e0e7ff !important;">
      <div class="card-body py-4">
        <div class="text-center mb-3">
          <div class="spinner-border" style="color:var(--asf-indigo);width:3rem;height:3rem;" role="status"></div>
          <div class="mt-3 font-weight-600" style="color:#1e293b;" id="progressMsg">Initialising scan…</div>
        </div>
        <div class="progress" style="height:6px;border-radius:99px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%;background:linear-gradient(90deg,var(--asf-indigo),var(--asf-violet));"></div>
        </div>
        <div class="text-center mt-2">
          <small class="text-muted" id="progressSub">This may take several minutes for full scans…</small>
        </div>
      </div>
    </div>

    <!-- Error card -->
    <div id="errorCard" class="card d-none mb-3" style="border:2px solid #fee2e2 !important;">
      <div class="card-body d-flex align-items-center" style="color:#dc2626;">
        <i class="fas fa-times-circle mr-3 fa-lg"></i>
        <div>
          <div class="font-weight-bold">Scan Failed</div>
          <div style="font-size:.83rem;" id="errorMsg"></div>
        </div>
      </div>
    </div>

    <!-- Results card -->
    <div id="resultCard" class="card d-none">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title">
          <i class="fas fa-shield-alt mr-2" style="color:#16a34a;"></i>
          Security Review: <code id="resTarget" style="font-size:.85rem;"></code>
        </span>
        <div>
          <span class="badge mr-1" id="resModel" style="background:#eef2ff;color:#6366f1;font-size:.7rem;"></span>
          <span class="badge" id="resStatus" style="background:#dcfce7;color:#16a34a;font-size:.7rem;">Completed</span>
        </div>
      </div>
      <div class="card-body">

        <!-- AI Analysis -->
        <div class="mb-4">
          <div class="d-flex align-items-center mb-2">
            <div style="width:28px;height:28px;border-radius:.5rem;background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));display:inline-flex;align-items:center;justify-content:center;margin-right:.5rem;">
              <i class="fas fa-robot text-white" style="font-size:.75rem;"></i>
            </div>
            <strong style="font-size:.88rem;color:#1e293b;">AI Triage Analysis</strong>
            <small class="text-muted ml-2" id="resTimestamp"></small>
          </div>
          <div id="resAnalysis"
               style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem 1.25rem;white-space:pre-wrap;font-size:.83rem;line-height:1.65;max-height:420px;overflow-y:auto;font-family:'Inter',sans-serif;">
          </div>
        </div>

        <!-- Scan errors -->
        <div id="errorsBox" class="d-none mb-3">
          <div class="alert alert-warning d-flex align-items-center" style="font-size:.8rem;">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <div><strong>Scan warnings:</strong> <span id="errorsMsg"></span></div>
          </div>
        </div>

        <!-- Raw output toggle -->
        <div>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#rawOutput">
            <i class="fas fa-terminal mr-1"></i>Raw Scan Output
          </button>
          <div class="collapse mt-2" id="rawOutput">
            <pre id="resRaw"
                 style="background:#0f172a;color:#4ade80;border-radius:.75rem;padding:1rem;font-size:.72rem;max-height:320px;overflow:auto;line-height:1.6;"></pre>
          </div>
        </div>

        <hr class="mt-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem;">
          <small class="text-muted">Job ID: <code id="resJobId">—</code></small>
          <div>
            <button class="btn btn-sm btn-outline-secondary mr-1" onclick="copyAnalysis()">
              <i class="fas fa-copy mr-1"></i>Copy Analysis
            </button>
            <a href="scan_jobs.php" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-history mr-1"></i>All Jobs
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Placeholder -->
    <div id="placeholder" class="card" style="border:2px dashed #e2e8f0 !important;background:transparent;">
      <div class="card-body text-center py-5">
        <div style="width:72px;height:72px;border-radius:1.25rem;background:linear-gradient(135deg,#eef2ff,#f5f3ff);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;">
          <i class="fas fa-search-plus fa-2x" style="color:#c7d2fe;"></i>
        </div>
        <h6 style="color:#1e293b;font-weight:700;">Ready to scan</h6>
        <p class="text-muted mb-0" style="font-size:.83rem;">
          Configure your target and select modules,<br>then click <strong>Run Security Review</strong>.
        </p>
      </div>
    </div>

  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
// ── Module option click toggle ────────────────────────────────────
document.querySelectorAll('.scan-module-option').forEach(wrap => {
  const card = wrap.querySelector('div');
  const cb   = wrap.querySelector('input[type=checkbox]');
  const applyStyle = () => {
    card.style.borderColor  = cb.checked ? '#6366f1' : '#dee2e6';
    card.style.background   = cb.checked ? '#f8f9ff' : '';
  };
  applyStyle();
  card.addEventListener('click', e => {
    if (e.target !== cb) cb.checked = !cb.checked;
    applyStyle();
  });
  cb.addEventListener('change', applyStyle);
});

// ── Form submit ───────────────────────────────────────────────────
const steps = [
  'Launching scan containers…',
  'Running nmap network scan…',
  'Running nikto DAST scan…',
  'Running sqlmap SQLi check…',
  'Sending output to Ollama…',
  'AI generating triage report…'
];
let stepIdx = 0, stepTimer;

function startProgress(types) {
  const msgs = ['Launching scan containers…'];
  if (types.includes('network')) msgs.push('Running nmap network scan…');
  if (types.includes('dast'))    msgs.push('Running nikto DAST scan…');
  if (types.includes('sqli'))    msgs.push('Running sqlmap SQLi check…');
  msgs.push('Sending output to Ollama…', 'AI generating triage report…');

  let i = 0;
  document.getElementById('progressMsg').textContent = msgs[0];
  stepTimer = setInterval(() => {
    i = Math.min(i + 1, msgs.length - 1);
    document.getElementById('progressMsg').textContent = msgs[i];
  }, 18000);
}

document.getElementById('scanForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const target = document.getElementById('target').value.trim();
  const cbs    = [...document.querySelectorAll('input[name="scan_types[]"]:checked')];
  const types  = cbs.map(c => c.value);

  if (!types.length) { toast('Select at least one scan module.', 'warning'); return; }

  // UI: scanning state
  document.getElementById('btnScan').disabled = true;
  document.getElementById('scanProgress').classList.remove('d-none');
  document.getElementById('resultCard').classList.add('d-none');
  document.getElementById('errorCard').classList.add('d-none');
  document.getElementById('placeholder').classList.add('d-none');
  startProgress(types);

  const fd = new FormData();
  fd.append('target', target);
  types.forEach(t => fd.append('scan_types[]', t));

  try {
    const resp = await fetch('scan_trigger.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd,
    });
    const data = await resp.json();

    clearInterval(stepTimer);
    document.getElementById('scanProgress').classList.add('d-none');

    if (data.error) {
      document.getElementById('errorCard').classList.remove('d-none');
      document.getElementById('errorMsg').textContent = data.error;
      toast('Scan failed: ' + data.error, 'danger');
    } else {
      document.getElementById('resTarget').textContent   = data.target || target;
      document.getElementById('resAnalysis').textContent = data.analysis || '(No analysis returned — check Ollama is running)';
      document.getElementById('resRaw').textContent      = data.raw_output || '(empty)';
      document.getElementById('resModel').textContent    = 'Model: ' + (data.model || 'unknown');
      document.getElementById('resJobId').textContent    = data.job_id || '—';
      document.getElementById('resTimestamp').textContent = data.timestamp ? data.timestamp.slice(0,19).replace('T',' ') + ' UTC' : '';
      if (data.scan_errors && data.scan_errors.length) {
        document.getElementById('errorsBox').classList.remove('d-none');
        document.getElementById('errorsMsg').textContent = data.scan_errors.join('; ');
      } else {
        document.getElementById('errorsBox').classList.add('d-none');
      }
      document.getElementById('resultCard').classList.remove('d-none');
      toast('Security review completed!', 'success');
    }
  } catch (err) {
    clearInterval(stepTimer);
    document.getElementById('scanProgress').classList.add('d-none');
    document.getElementById('errorCard').classList.remove('d-none');
    document.getElementById('errorMsg').textContent = err.message;
    toast('Request failed: ' + err.message, 'danger');
  } finally {
    document.getElementById('btnScan').disabled = false;
  }
});

function copyAnalysis() {
  const text = document.getElementById('resAnalysis').textContent;
  navigator.clipboard.writeText(text).then(() => toast('Analysis copied to clipboard.', 'info'));
}
</script>
JS;
?>
<?php require_once '../views/partials/footer.php'; ?>
