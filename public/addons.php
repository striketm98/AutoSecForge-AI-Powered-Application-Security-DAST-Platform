<?php
require_once '../src/auth.php';
require_auth();

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403); exit('Access denied.');
}

$page_title = 'Modules';

$env = @parse_ini_file('/var/www/html/.env', false, INI_SCANNER_RAW) ?: [];
$mcp = rtrim($env['MCP_URL'] ?? 'http://mcp-router:6300', '/');
$ai  = rtrim($env['AI_AGENT_URL'] ?? 'http://ai-agent:6400', '/');
$oasm= rtrim($env['OASM_URL'] ?? 'http://oasm:6200', '/');

// Modules that expose an HTTP health endpoint → pinged in parallel below.
$http_modules = [
    ['Ollama LLM',  'AI Engine',     'fa-robot',        '#6366f1', 'http://ollama:11434/api/tags',        'Local large-language model serving AI triage.'],
    ['AI Agent',    'AI Engine',     'fa-brain',        '#8b5cf6', "$ai/health",                          'Ollama-backed triage & structured-findings API.'],
    ['MCP Router',  'Orchestration', 'fa-diagram-project','#0ea5e9', "$mcp/health",                       'HackStrike orchestrator — drives the scanners.'],
    ['OWASP ZAP',   'Web DAST',      'fa-bug',          '#06b6d4', 'http://zap:8090/',                     'Dynamic web app scanner (spider + active scan).'],
    ['Trivy',       'Container SCA', 'fa-cube',         '#0891b2', 'http://trivy:8081/healthz',            'Container & filesystem CVE scanner.'],
    ['SonarQube',   'Code SAST',     'fa-code',         '#16a34a', 'http://sonarqube:9000/api/system/status','Static application security testing.'],
    ['MobSF',       'Mobile',        'fa-mobile-alt',   '#d97706', 'http://mobsf:8000/',                   'Mobile app (APK/IPA) static analysis.'],
    ['OASM',        'Attack Surface','fa-crosshairs',   '#db2777', "$oasm/health",                        'Open attack-surface mapping.'],
];

// Exec-only scanner containers (no HTTP) — managed via the MCP router.
$exec_modules = [
    ['nmap',          'Network',  'fa-network-wired'],
    ['nikto',         'Web DAST', 'fa-spider'],
    ['sqlmap',        'SQLi',     'fa-database'],
    ['sonar-scanner', 'SAST CLI', 'fa-magnifying-glass-chart'],
];

/** Ping a set of URLs in parallel; return [url => http_code|0]. */
function ping_all(array $urls): array {
    $mh = curl_multi_init(); $handles = []; $out = [];
    foreach ($urls as $u) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY=>false, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>3, CURLOPT_CONNECTTIMEOUT=>2,
            CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_FOLLOWLOCATION=>true,
        ]);
        curl_multi_add_handle($mh, $ch); $handles[$u] = $ch;
    }
    do { $status = curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1.0); }
    while ($running && $status === CURLM_OK);
    foreach ($handles as $u => $ch) {
        $out[$u] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

$codes  = ping_all(array_column($http_modules, 4));
$online = 0;
foreach ($http_modules as $m) if (($codes[$m[4]] ?? 0) >= 200 && ($codes[$m[4]] ?? 0) < 500) $online++;
?>
<?php require_once '../views/partials/header.php'; ?>

<div id="pageActions">
  <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()"><i class="fas fa-sync mr-1"></i>Refresh</button>
</div>

<div class="row">
  <div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body d-flex align-items-center">
    <div style="width:46px;height:46px;border-radius:.75rem;background:#16a34a1a;display:flex;align-items:center;justify-content:center;margin-right:.85rem;"><i class="fas fa-circle-check" style="color:#16a34a;"></i></div>
    <div><div style="font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1;"><?= $online ?>/<?= count($http_modules) ?></div><div class="text-muted" style="font-size:.74rem;">Services Online</div></div>
  </div></div></div>
  <div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body d-flex align-items-center">
    <div style="width:46px;height:46px;border-radius:.75rem;background:#6366f11a;display:flex;align-items:center;justify-content:center;margin-right:.85rem;"><i class="fas fa-cubes" style="color:#6366f1;"></i></div>
    <div><div style="font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1;"><?= count($http_modules)+count($exec_modules) ?></div><div class="text-muted" style="font-size:.74rem;">Integrated Modules</div></div>
  </div></div></div>
  <div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body d-flex align-items-center">
    <div style="width:46px;height:46px;border-radius:.75rem;background:#0ea5e91a;display:flex;align-items:center;justify-content:center;margin-right:.85rem;"><i class="fas fa-terminal" style="color:#0ea5e9;"></i></div>
    <div><div style="font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1;"><?= count($exec_modules) ?></div><div class="text-muted" style="font-size:.74rem;">Scanner Containers</div></div>
  </div></div></div>
</div>

<div class="card mb-4">
  <div class="card-header"><span class="card-title"><i class="fas fa-puzzle-piece mr-2" style="color:var(--asf-indigo);"></i>Service Modules</span></div>
  <div class="card-body">
    <div class="row">
      <?php foreach ($http_modules as $m):
        [$name,$cat,$icon,$col,$url,$desc] = $m;
        $code = $codes[$url] ?? 0;
        $ok   = $code >= 200 && $code < 500;
        $sc   = $ok ? '#16a34a' : '#dc2626';
        $sbg  = $ok ? '#dcfce7' : '#fee2e2';
        $stxt = $ok ? 'Online' : ($code ? "HTTP $code" : 'Offline');
      ?>
      <div class="col-md-6 col-xl-4 mb-3">
        <div class="card h-100" style="border:1px solid #f1f5f9 !important;">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-2">
              <div style="width:40px;height:40px;border-radius:.6rem;background:<?=$col?>1a;display:flex;align-items:center;justify-content:center;"><i class="fas <?=$icon?>" style="color:<?=$col?>;"></i></div>
              <span class="badge" style="background:<?=$sbg?>;color:<?=$sc?>;font-size:.66rem;"><i class="fas fa-circle mr-1" style="font-size:.5rem;"></i><?= $stxt ?></span>
            </div>
            <div style="font-weight:700;color:#1e293b;font-size:.92rem;"><?= htmlspecialchars($name) ?></div>
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;"><?= htmlspecialchars($cat) ?></div>
            <div class="text-muted mt-2" style="font-size:.76rem;line-height:1.45;"><?= htmlspecialchars($desc) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><span class="card-title"><i class="fas fa-terminal mr-2" style="color:var(--asf-indigo);"></i>Scanner Containers <small class="text-muted">(exec-only, via MCP)</small></span></div>
  <div class="card-body">
    <div class="row">
      <?php foreach ($exec_modules as [$name,$cat,$icon]): ?>
      <div class="col-6 col-md-3 mb-2">
        <div class="d-flex align-items-center p-2 border rounded">
          <div style="width:34px;height:34px;border-radius:.5rem;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin-right:.6rem;"><i class="fas <?=$icon?>" style="color:#6366f1;"></i></div>
          <div><div style="font-weight:600;font-size:.82rem;color:#1e293b;"><?= htmlspecialchars($name) ?></div><div class="text-muted" style="font-size:.66rem;"><?= htmlspecialchars($cat) ?></div></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <small class="text-muted">These run as idle containers; the MCP router executes the scan commands inside them on demand.</small>
  </div>
</div>

<?php require_once '../views/partials/footer.php'; ?>
