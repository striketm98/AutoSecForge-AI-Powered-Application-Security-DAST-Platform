<?php
require_once '../src/auth.php';
require_once '../src/helpers.php';
require_auth();

if (!in_array($_SESSION['user_role'] ?? '', ['admin','manager','analyst'])) {
    http_response_code(403); exit('Access denied.');
}

$page_title = 'Attack Surface';

/**
 * Sensitive / high-exposure services. Anything found open here is risk-flagged.
 * key = lowercase nmap service name; we also match a few well-known port numbers.
 */
function asf_surface_risk(int $port, string $service): array {
    // [severity, label, why, remediation]
    $svc = strtolower($service);
    $map = [
        'telnet'        => ['critical','Telnet','Clear-text remote shell — credentials travel unencrypted.','Disable Telnet; use SSH.'],
        'ftp'           => ['high','FTP','Often anonymous / clear-text; a frequent foothold.','Replace with SFTP/FTPS or restrict by IP.'],
        'microsoft-ds'  => ['high','SMB (445)','File-sharing exposed to the internet is high-risk (e.g. EternalBlue).','Never expose SMB publicly; firewall to internal only.'],
        'netbios-ssn'   => ['high','NetBIOS','Legacy Windows file/printer sharing exposed.','Block 137-139 at the perimeter.'],
        'ms-wbt-server' => ['high','RDP','Remote Desktop is heavily brute-forced and exploited.','Put behind VPN; never expose 3389 directly.'],
        'vnc'           => ['high','VNC','Remote desktop, frequently unauthenticated.','Tunnel over SSH/VPN; require auth.'],
        'mysql'         => ['high','MySQL','Database directly reachable from the internet.','Bind to localhost / private net; firewall 3306.'],
        'mariadb'       => ['high','MariaDB','Database directly reachable from the internet.','Bind to localhost / private net; firewall 3306.'],
        'postgresql'    => ['high','PostgreSQL','Database directly reachable from the internet.','Bind to localhost / private net; firewall 5432.'],
        'mongodb'       => ['high','MongoDB','Historically unauthenticated by default.','Enable auth; firewall 27017 to private net.'],
        'redis'         => ['high','Redis','No auth by default → RCE / data theft.','Require auth; bind to localhost; firewall 6379.'],
        'memcached'     => ['high','Memcached','UDP amplification + data exposure.','Disable UDP; bind to localhost.'],
        'elasticsearch' => ['high','Elasticsearch','Open clusters leak data and allow RCE.','Enable security; firewall 9200/9300.'],
        'ms-sql-s'      => ['high','MSSQL','Database directly reachable from the internet.','Firewall 1433; use private networking.'],
        'oracle-tns'    => ['high','Oracle TNS','Database listener exposed.','Restrict 1521 to private net.'],
        'docker'        => ['critical','Docker API','Unauthenticated Docker socket = full host takeover.','Never expose 2375/2376 publicly.'],
        'rpcbind'       => ['medium','rpcbind','Information disclosure / amplification vector.','Block 111 at the perimeter.'],
        'snmp'          => ['medium','SNMP','Default community strings leak device info.','Use SNMPv3; firewall 161.'],
        'ldap'          => ['medium','LDAP','Directory service exposed.','Use LDAPS; restrict to private net.'],
        'smtp'          => ['low','SMTP','Mail service — check for open relay.','Require auth; disable open relay.'],
        'http'          => ['low','HTTP','Plain-text web service.','Redirect to HTTPS; add security headers.'],
        'http-proxy'    => ['medium','HTTP Proxy','Open proxy can be abused.','Require auth; restrict access.'],
    ];
    if (isset($map[$svc])) {
        return ['severity'=>$map[$svc][0],'label'=>$map[$svc][1],'why'=>$map[$svc][2],'fix'=>$map[$svc][3]];
    }
    // Port-based fallbacks for unfingerprinted services
    $byPort = [23=>'telnet',21=>'ftp',445=>'microsoft-ds',3389=>'ms-wbt-server',5900=>'vnc',
               3306=>'mysql',5432=>'postgresql',27017=>'mongodb',6379=>'redis',11211=>'memcached',
               9200=>'elasticsearch',1433=>'ms-sql-s',1521=>'oracle-tns',2375=>'docker',2376=>'docker',
               139=>'netbios-ssn',111=>'rpcbind',161=>'snmp',389=>'ldap'];
    if (isset($byPort[$port]) && isset($map[$byPort[$port]])) {
        $m = $map[$byPort[$port]];
        return ['severity'=>$m[0],'label'=>$m[1],'why'=>$m[2],'fix'=>$m[3]];
    }
    return ['severity'=>'info','label'=>$service ?: 'service','why'=>'','fix'=>''];
}

// ── AJAX: discover attack surface (nmap -sV via MCP /scan/network) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $target = trim($_POST['target'] ?? '');
    if ($target === '') { echo json_encode(['error'=>'Target is required.']); exit; }
    $client_id = asf_valid_client_id($_POST['client_id'] ?? null);

    // SSRF guard — public hosts/IPs only
    $host = parse_url(strpos($target,'http')===0 ? $target : "http://$target", PHP_URL_HOST) ?: $target;
    foreach (['127.','10.','192.168.','172.16.','172.17.','172.18.','172.19.','172.2','172.3','169.254.','localhost','::1'] as $p) {
        if (str_starts_with($host,$p) || $host==='localhost') {
            echo json_encode(['error'=>'Private/internal targets are not permitted.']); exit;
        }
    }
    // normalise to bare host for nmap
    $target = $host;

    $env     = @parse_ini_file('/var/www/html/.env', false, INI_SCANNER_RAW) ?: [];
    $mcp_url = rtrim($env['MCP_URL'] ?? 'http://mcp-router:6300', '/');

    $payload = json_encode(['target'=>$target, 'flags'=>['-sV','-T4','--open']]);
    $ch = curl_init("$mcp_url/scan/network");
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>300,
    ]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) { echo json_encode(['error'=>"MCP unreachable: $err"]); exit; }

    $mcp = json_decode($raw, true) ?: [];
    if (!empty($mcp['error']))      { echo json_encode(['error'=>$mcp['error']]); exit; }
    if (empty($mcp['success']))     { echo json_encode(['error'=>$mcp['error'] ?? 'nmap scan failed.']); exit; }

    $stdout = $mcp['stdout'] ?? '';

    // ── Parse nmap XML into a port/service inventory ──
    $ports = []; $resolved = $target;
    $xml = @simplexml_load_string($stdout);
    if ($xml !== false) {
        foreach ($xml->host as $h) {
            foreach ($h->hostnames->hostname ?? [] as $hn) {
                if ((string)$hn['name'] !== '') { $resolved = (string)$hn['name']; break; }
            }
            if (!isset($h->ports)) continue;
            foreach ($h->ports->port ?? [] as $p) {
                if ((string)$p->state['state'] !== 'open') continue;
                $svc  = $p->service;
                $name = (string)($svc['name'] ?? '');
                $port = (int)$p['portid'];
                $risk = asf_surface_risk($port, $name);
                $ports[] = [
                    'port'     => $port,
                    'proto'    => (string)$p['protocol'],
                    'service'  => $name ?: '—',
                    'product'  => trim((string)($svc['product'] ?? '')),
                    'version'  => trim((string)($svc['version'] ?? '')),
                    'severity' => $risk['severity'],
                    'why'      => $risk['why'],
                    'fix'      => $risk['fix'],
                    'label'    => $risk['label'],
                ];
            }
        }
    }

    usort($ports, fn($a,$b)=>$a['port']<=>$b['port']);

    // ── OASM service: passive subdomains + DNS + HTTP header-gap findings ──
    $oasm_url = rtrim(getenv('OASM_URL') ?: ($env['OASM_URL'] ?? 'http://oasm:6200'), '/');
    $oasm = ['subdomains'=>[], 'dns'=>[], 'http'=>[], 'findings'=>[]];
    $och = curl_init("$oasm_url/discover");
    curl_setopt_array($och, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(['target'=>$resolved]),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60,
    ]);
    $oraw = curl_exec($och); curl_close($och);
    if ($oraw) {
        $od = json_decode($oraw, true);
        if (is_array($od) && empty($od['error'])) $oasm = array_merge($oasm, $od);
    }
    $surface_findings = is_array($oasm['findings'] ?? null) ? $oasm['findings'] : [];

    $sevRank = ['critical'=>0,'high'=>1,'medium'=>2,'low'=>3,'info'=>4];
    $counts  = ['critical'=>0,'high'=>0,'medium'=>0,'low'=>0,'info'=>0];
    foreach ($ports as $p) { $counts[$p['severity']] = ($counts[$p['severity']] ?? 0) + 1; }
    foreach ($surface_findings as $f) {
        $s = $f['severity'] ?? 'info'; $counts[$s] = ($counts[$s] ?? 0) + 1;
    }
    $risky = $counts['critical']+$counts['high']+$counts['medium'];

    // ── Build a plain-text surface summary (stored as the job's "analysis") ──
    $lines = ["Attack surface for {$resolved}", str_repeat('─',40)];
    $lines[] = count($ports) . " open TCP port(s); {$risky} flagged as sensitive.";
    foreach ($ports as $p) {
        $banner = trim($p['product'].' '.$p['version']);
        $lines[] = sprintf('  %5d/%s  %-14s %s%s',
            $p['port'], $p['proto'], $p['service'],
            $banner !== '' ? "($banner) " : '',
            $p['severity'] !== 'info' ? "[".strtoupper($p['severity'])."]" : '');
    }
    if ($risky) {
        $lines[] = ''; $lines[] = 'Recommendation: close or firewall every service that does not need to be public.';
    }

    // OASM discovery summary
    if (!empty($oasm['subdomains'])) {
        $lines[] = ''; $lines[] = 'Subdomains (certificate transparency): ' . count($oasm['subdomains']);
        foreach (array_slice($oasm['subdomains'], 0, 40) as $s) $lines[] = '  • ' . $s;
        if (count($oasm['subdomains']) > 40) $lines[] = '  … and ' . (count($oasm['subdomains']) - 40) . ' more';
    }
    if (!empty($oasm['dns'])) {
        $lines[] = ''; $lines[] = 'DNS records:';
        foreach ($oasm['dns'] as $rt => $vals) {
            if (!empty($vals)) $lines[] = sprintf('  %-5s %s', $rt, implode(', ', (array)$vals));
        }
    }
    if (!empty($oasm['http']['server'])) {
        $lines[] = ''; $lines[] = 'HTTP server banner: ' . $oasm['http']['server'];
    }
    if ($surface_findings) {
        $lines[] = ''; $lines[] = 'Security header gaps: ' . count($surface_findings) . ' (see findings).';
    }
    $analysis = implode("\n", $lines);

    // ── Persist job + risk findings ──
    $job_id = null;
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO scan_jobs (target, scan_types, raw_output, analysis, model, triggered_by, client_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$resolved, 'attack_surface', $stdout, $analysis, 'nmap + oasm',
                        $_SESSION['user_id'] ?? null, $client_id, 'completed']);
        $job_id = $pdo->lastInsertId();
        asf_audit('scan.attack_surface', "target=$resolved ports=".count($ports)." job=$job_id client=$client_id");

        $fstmt = $pdo->prepare(
            'INSERT INTO findings (scan_job_id, title, description, severity, affected_url, remediation)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($ports as $p) {
            if ($p['severity'] === 'info') continue;     // only persist real exposure
            $banner = trim($p['product'].' '.$p['version']);
            try {
                $fstmt->execute([
                    $job_id,
                    "Exposed {$p['label']} on port {$p['port']}/{$p['proto']}",
                    ($p['why'] ?: 'Service reachable from the public internet.') .
                        ($banner !== '' ? "  Detected: {$banner}." : ''),
                    $p['severity'],
                    "{$resolved}:{$p['port']}",
                    $p['fix'],
                ]);
            } catch (Throwable) {}
        }
        // OASM HTTP security-header gaps → findings
        $allowed_sev = ['critical','high','medium','low','info'];
        foreach ($surface_findings as $f) {
            $sev = in_array($f['severity'] ?? 'low', $allowed_sev, true) ? $f['severity'] : 'low';
            try {
                $fstmt->execute([
                    $job_id,
                    mb_substr($f['title'] ?? 'Surface finding', 0, 500),
                    $f['description'] ?? '',
                    $sev,
                    mb_substr($f['affected_url'] ?? $resolved, 0, 1000),
                    $f['remediation'] ?? '',
                ]);
            } catch (Throwable) {}
        }
        asf_notify(
            asf_scan_recipients(isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $client_id),
            'Attack surface report ready', $resolved,
            'report.php?export=' . $job_id . '&format=html', 'success'
        );
    } catch (Throwable $e) {
        echo json_encode(['error'=>null,'db_warning'=>$e->getMessage(),
            'target'=>$resolved,'ports'=>$ports,'counts'=>$counts,'analysis'=>$analysis,'job_id'=>null]); exit;
    }

    echo json_encode([
        'target'     => $resolved,
        'ports'      => $ports,
        'counts'     => $counts,
        'risky'      => $risky,
        'analysis'   => $analysis,
        'raw'        => $stdout,
        'job_id'     => $job_id,
        'subdomains' => $oasm['subdomains'] ?? [],
        'dns'        => $oasm['dns'] ?? [],
        'http'       => $oasm['http'] ?? [],
        'timestamp'  => gmdate('c'),
    ]);
    exit;
}

$clients = asf_clients();
?>
<?php require_once '../views/partials/header.php'; ?>

<div id="pageActions">
  <a href="scan_jobs.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-history mr-1"></i>History
  </a>
</div>

<div class="row">

  <!-- ── Left: discovery panel ──────────────────────────────────── -->
  <div class="col-lg-4 mb-4">
    <div class="card">
      <div class="card-header card-header-gradient">
        <span class="card-title text-white"><i class="fas fa-crosshairs mr-2"></i>Surface Discovery</span>
      </div>
      <div class="card-body">
        <form id="oasmForm">
          <div class="form-group">
            <label class="font-weight-bold" style="font-size:.82rem;color:#374151;">
              Target host <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text bg-transparent border-right-0"><i class="fas fa-globe text-muted"></i></span>
              </div>
              <input type="text" class="form-control border-left-0" id="target" name="target"
                     placeholder="example.com or 203.0.113.1" autocomplete="off" required
                     style="border-radius:0 .5rem .5rem 0;">
            </div>
            <small class="text-muted">Public hosts &amp; IPs only. Maps open TCP ports &amp; service versions.</small>
          </div>
          <div class="form-group">
            <label class="font-weight-bold" style="font-size:.82rem;color:#374151;"><i class="fas fa-building mr-1 text-muted"></i>Client</label>
            <select class="form-control" id="clientId" name="client_id">
              <option value="">Internal / no client</option>
              <?php foreach ($clients as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?><?= $c['company'] ? ' — ' . htmlspecialchars($c['company']) : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-asf btn-block py-3 mt-1" id="btnMap">
            <i class="fas fa-satellite-dish mr-2"></i>Map Attack Surface
          </button>
        </form>
      </div>
    </div>

    <div class="card mt-3" style="background:#f8faff;border:1px solid #e0e7ff !important;">
      <div class="card-body py-3">
        <div class="d-flex align-items-start">
          <i class="fas fa-info-circle mr-2 mt-1" style="color:var(--asf-indigo);font-size:1.05rem;"></i>
          <div style="font-size:.78rem;color:#475569;">
            <strong style="color:#1e293b;">What this does</strong><br>
            Runs an <code style="font-size:.72rem;">nmap -sV</code> service sweep, then classifies each
            exposed service by risk. Sensitive services (databases, RDP, SMB, Redis…) are flagged and saved
            as findings for the report.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Right: results panel ───────────────────────────────────── -->
  <div class="col-lg-8 mb-4">

    <!-- Progress -->
    <div id="mapProgress" class="card d-none mb-3" style="border:2px solid #e0e7ff !important;">
      <div class="card-body py-4 text-center">
        <div class="spinner-border" style="color:var(--asf-indigo);width:3rem;height:3rem;" role="status"></div>
        <div class="mt-3 font-weight-bold" style="color:#1e293b;" id="mapMsg">Sweeping ports &amp; fingerprinting services…</div>
        <small class="text-muted">A service sweep of the top 1000 ports can take 30–90 seconds.</small>
      </div>
    </div>

    <!-- Error -->
    <div id="errorCard" class="card d-none mb-3" style="border:2px solid #fee2e2 !important;">
      <div class="card-body d-flex align-items-center" style="color:#dc2626;">
        <i class="fas fa-times-circle mr-3 fa-lg"></i>
        <div><div class="font-weight-bold">Discovery Failed</div><div style="font-size:.83rem;" id="errorMsg"></div></div>
      </div>
    </div>

    <!-- Results -->
    <div id="resultWrap" class="d-none">
      <!-- Summary cards -->
      <div class="row mb-3" id="summaryRow"></div>

      <!-- Ports table -->
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="card-title"><i class="fas fa-door-open mr-2" style="color:var(--asf-indigo);"></i>Exposed Services on <code id="resTarget" style="font-size:.82rem;"></code></span>
          <span class="badge" id="resJobBadge" style="background:#eef2ff;color:#6366f1;font-size:.7rem;"></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.84rem;">
              <thead>
                <tr><th class="pl-4 py-3">Port</th><th class="py-3">Service</th><th class="py-3">Product / Version</th><th class="py-3 pr-4 text-right">Risk</th></tr>
              </thead>
              <tbody id="portsBody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- OASM discovery (subdomains / DNS / HTTP) -->
      <div id="discoveryCard" class="card mb-3 d-none">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-sitemap mr-2" style="color:#0891b2;"></i>Discovery</span>
        </div>
        <div class="card-body" id="discoveryBody"></div>
      </div>

      <!-- Raw -->
      <div class="card">
        <div class="card-body">
          <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#rawOut">
            <i class="fas fa-terminal mr-1"></i>Raw nmap (XML)
          </button>
          <div class="collapse mt-2" id="rawOut">
            <pre id="resRaw" style="background:#0f172a;color:#4ade80;border-radius:.75rem;padding:1rem;font-size:.7rem;max-height:320px;overflow:auto;line-height:1.5;"></pre>
          </div>
          <hr>
          <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
            <small class="text-muted">Saved as scan job <code id="resJobId">—</code> · findings added to reports</small>
            <a href="scan_jobs.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-history mr-1"></i>All Jobs</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Placeholder -->
    <div id="placeholder" class="card" style="border:2px dashed #e2e8f0 !important;background:transparent;">
      <div class="card-body text-center py-5">
        <div style="width:72px;height:72px;border-radius:1.25rem;background:linear-gradient(135deg,#eef2ff,#f5f3ff);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;">
          <i class="fas fa-crosshairs fa-2x" style="color:#c7d2fe;"></i>
        </div>
        <h6 style="color:#1e293b;font-weight:700;">Map your exposure</h6>
        <p class="text-muted mb-0" style="font-size:.83rem;">
          Enter a public host and click <strong>Map Attack Surface</strong><br>to inventory open ports &amp; risky services.
        </p>
      </div>
    </div>

  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
const SEV = {
  critical:{bg:'#fee2e2',fg:'#dc2626',ic:'#fca5a5'},
  high:    {bg:'#fef3c7',fg:'#d97706',ic:'#fcd34d'},
  medium:  {bg:'#dbeafe',fg:'#2563eb',ic:'#93c5fd'},
  low:     {bg:'#dcfce7',fg:'#16a34a',ic:'#86efac'},
  info:    {bg:'#f1f5f9',fg:'#64748b',ic:'#cbd5e1'}
};

document.getElementById('oasmForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const target = document.getElementById('target').value.trim();
  if(!target){ toast('Enter a target host.','warning'); return; }

  document.getElementById('btnMap').disabled = true;
  document.getElementById('mapProgress').classList.remove('d-none');
  document.getElementById('resultWrap').classList.add('d-none');
  document.getElementById('errorCard').classList.add('d-none');
  document.getElementById('placeholder').classList.add('d-none');

  const fd = new FormData(); fd.append('target', target);
  fd.append('client_id', document.getElementById('clientId').value);
  try {
    const resp = await fetch('oasm.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
    const data = await resp.json();
    document.getElementById('mapProgress').classList.add('d-none');

    if (data.error) {
      document.getElementById('errorCard').classList.remove('d-none');
      document.getElementById('errorMsg').textContent = data.error;
      toast('Discovery failed: ' + data.error, 'danger');
      return;
    }
    render(data);
    toast('Attack surface mapped — ' + (data.ports?.length||0) + ' open ports.', 'success');
  } catch (err) {
    document.getElementById('mapProgress').classList.add('d-none');
    document.getElementById('errorCard').classList.remove('d-none');
    document.getElementById('errorMsg').textContent = err.message;
    toast('Request failed: ' + err.message, 'danger');
  } finally {
    document.getElementById('btnMap').disabled = false;
  }
});

function statCard(label, value, color, icon){
  return `<div class="col-6 col-md-3 mb-2">
    <div class="card stat-card h-100"><div class="card-body d-flex align-items-center" style="gap:.6rem;padding:.9rem 1rem;">
      <div class="stat-icon" style="width:42px;height:42px;font-size:1.1rem;background:${color};">${icon}</div>
      <div><div class="stat-value" style="font-size:1.5rem;">${value}</div>
      <div class="stat-label" style="font-size:.66rem;">${label}</div></div>
    </div></div></div>`;
}

function render(d){
  const c = d.counts || {};
  const total = (d.ports||[]).length;
  document.getElementById('summaryRow').innerHTML =
    statCard('Open Ports', total, 'linear-gradient(135deg,#6366f1,#8b5cf6)', '<i class="fas fa-door-open"></i>') +
    statCard('Critical', c.critical||0, 'linear-gradient(135deg,#b91c1c,#ef4444)', '<i class="fas fa-skull-crossbones"></i>') +
    statCard('High', c.high||0, 'linear-gradient(135deg,#d97706,#f59e0b)', '<i class="fas fa-exclamation-triangle"></i>') +
    statCard('Sensitive', d.risky||0, 'linear-gradient(135deg,#0891b2,#06b6d4)', '<i class="fas fa-shield-alt"></i>');

  document.getElementById('resTarget').textContent = d.target || '';
  document.getElementById('resJobId').textContent  = d.job_id || '—';
  document.getElementById('resJobBadge').textContent = total + ' service' + (total===1?'':'s');
  document.getElementById('resRaw').textContent    = d.raw || '(empty)';

  const body = document.getElementById('portsBody');
  if (!total) {
    body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No open ports detected on the top 1000.</td></tr>';
  } else {
    body.innerHTML = (d.ports||[]).map(p => {
      const s = SEV[p.severity] || SEV.info;
      const banner = [p.product, p.version].filter(Boolean).join(' ') || '<span class="text-muted">—</span>';
      const riskBadge = p.severity === 'info'
        ? '<span class="badge" style="background:#f1f5f9;color:#64748b;font-size:.66rem;">informational</span>'
        : `<span class="badge" style="background:${s.bg};color:${s.fg};font-size:.66rem;text-transform:uppercase;font-weight:700;">${p.severity}</span>`;
      const tip = p.why ? ` title="${p.why.replace(/"/g,'&quot;')}"` : '';
      return `<tr${tip}>
        <td class="pl-4"><code style="color:#1e293b;font-weight:600;">${p.port}/${p.proto}</code></td>
        <td><span style="font-weight:600;color:#1e293b;">${p.service}</span>${p.label && p.severity!=='info' ? ` <span class="text-muted" style="font-size:.72rem;">(${p.label})</span>`:''}</td>
        <td style="font-size:.8rem;color:#475569;">${banner}</td>
        <td class="pr-4 text-right">${riskBadge}</td>
      </tr>`;
    }).join('');
  }
  renderDiscovery(d);
  document.getElementById('resultWrap').classList.remove('d-none');
}

function oesc(s){ const e=document.createElement('div'); e.textContent=s==null?'':s; return e.innerHTML; }

function renderDiscovery(d){
  const card = document.getElementById('discoveryCard');
  const body = document.getElementById('discoveryBody');
  const subs = d.subdomains || [], dns = d.dns || {}, http = d.http || {};
  const hasAny = subs.length || (dns && Object.values(dns).some(v=>v&&v.length)) || (http && http.server);
  if (!hasAny){ card.classList.add('d-none'); return; }
  let html = '';
  if (subs.length){
    html += '<div class="mb-3"><div style="font-weight:700;color:#1e293b;font-size:.84rem;" class="mb-1">'+
      '<i class="fas fa-network-wired mr-1" style="color:#6366f1;"></i>Subdomains <span class="badge" style="background:#eef2ff;color:#6366f1;">'+subs.length+'</span></div>'+
      '<div style="max-height:180px;overflow:auto;font-size:.78rem;line-height:1.7;">'+
      subs.map(s=>'<code style="margin-right:.6rem;color:#334155;">'+oesc(s)+'</code>').join('')+'</div></div>';
  }
  const dnsRows = Object.entries(dns).filter(([,v])=>v&&v.length);
  if (dnsRows.length){
    html += '<div class="mb-3"><div style="font-weight:700;color:#1e293b;font-size:.84rem;" class="mb-1"><i class="fas fa-server mr-1" style="color:#0ea5e9;"></i>DNS records</div>'+
      '<table class="table table-sm mb-0" style="font-size:.78rem;"><tbody>'+
      dnsRows.map(([k,v])=>'<tr><th style="width:60px;">'+oesc(k)+'</th><td>'+v.map(oesc).join('<br>')+'</td></tr>').join('')+
      '</tbody></table></div>';
  }
  if (http && http.server){
    html += '<div><span style="font-weight:700;color:#1e293b;font-size:.84rem;"><i class="fas fa-globe mr-1" style="color:#16a34a;"></i>HTTP banner:</span> <code>'+oesc(http.server)+'</code>'+
      (http.url?' <span class="text-muted" style="font-size:.74rem;">('+oesc(http.url)+')</span>':'')+'</div>';
  }
  body.innerHTML = html;
  card.classList.remove('d-none');
}
</script>
JS;
?>
<?php require_once '../views/partials/footer.php'; ?>
