<?php
require_once '../src/auth.php';
require_auth();

if (!in_array($_SESSION['user_role'] ?? '', ['admin','manager','analyst','auditor'])) {
    http_response_code(403); exit('Access denied.');
}

$page_title = 'Compliance';

// Live findings posture (drives the summary cards)
$sev = ['critical'=>0,'high'=>0,'medium'=>0,'low'=>0,'info'=>0]; $db_error = null;
try {
    $pdo = Database::getInstance();
    $rs = $pdo->query("SELECT severity, COUNT(*) n FROM findings GROUP BY severity")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($sev as $k=>$v) $sev[$k] = (int)($rs[$k] ?? 0);
} catch (Throwable $e) { $db_error = $e->getMessage(); }
$total = array_sum($sev);

// OWASP Top 10 (2021) → which AutoSecForge modules provide coverage
$owasp = [
    ['A01','Broken Access Control',            ['OWASP ZAP','Manual'],              'partial'],
    ['A02','Cryptographic Failures',           ['OWASP ZAP','SonarQube'],           'covered'],
    ['A03','Injection',                        ['sqlmap','OWASP ZAP','SonarQube'],  'covered'],
    ['A04','Insecure Design',                  ['SonarQube','Manual'],              'partial'],
    ['A05','Security Misconfiguration',        ['nikto','nmap','OWASP ZAP'],        'covered'],
    ['A06','Vulnerable & Outdated Components', ['Trivy','SonarQube'],               'covered'],
    ['A07','Identification & Auth Failures',   ['OWASP ZAP','Manual'],              'partial'],
    ['A08','Software & Data Integrity',        ['Trivy','SonarQube'],               'covered'],
    ['A09','Logging & Monitoring Failures',    ['Audit Log','Manual'],              'partial'],
    ['A10','Server-Side Request Forgery',      ['OWASP ZAP','Manual'],              'partial'],
];
$cov_style = ['covered'=>['Covered','#16a34a','#dcfce7'],'partial'=>['Partial','#d97706','#fef3c7'],'manual'=>['Manual','#64748b','#f1f5f9']];

$standards = [
    ['PCI DSS 4.0',  'fa-credit-card', 'Req. 6 (secure dev), 11 (vuln scans & pentests) supported via DAST/SAST/SCA scans + reports.'],
    ['ISO/IEC 27001','fa-certificate', 'A.8.8 technical vulnerability management & A.8.25 secure development evidence via scan history + audit log.'],
    ['GDPR Art. 32', 'fa-user-shield', 'Security of processing — demonstrable testing of systems handling personal data.'],
    ['OWASP ASVS',   'fa-list-check',  'Application Security Verification Standard mapping through findings triage.'],
];
?>
<?php require_once '../views/partials/header.php'; ?>

<?php if ($db_error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($db_error) ?></div><?php endif; ?>

<!-- Findings posture -->
<div class="row">
  <?php
  $cards = [
    ['Total Findings', $total,            'fa-bug',            '#6366f1'],
    ['Critical',       $sev['critical'],  'fa-radiation',      '#b91c1c'],
    ['High',           $sev['high'],      'fa-triangle-exclamation','#ea580c'],
    ['Medium',         $sev['medium'],    'fa-circle-exclamation', '#d97706'],
    ['Low / Info',     $sev['low']+$sev['info'], 'fa-circle-info', '#2563eb'],
  ];
  foreach ($cards as [$label,$val,$icon,$col]): ?>
  <div class="col col-6 col-lg mb-3">
    <div class="card h-100" style="border:1px solid #f1f5f9 !important;">
      <div class="card-body py-3 text-center">
        <i class="fas <?=$icon?> mb-1" style="color:<?=$col?>;font-size:1.1rem;"></i>
        <div style="font-size:1.4rem;font-weight:800;color:#1e293b;line-height:1.1;"><?= (int)$val ?></div>
        <div class="text-muted" style="font-size:.7rem;"><?= $label ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- OWASP coverage matrix -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="card-title"><i class="fas fa-shield-halved mr-2" style="color:var(--asf-indigo);"></i>OWASP Top 10 (2021) — Coverage Matrix</span>
    <span class="badge" style="background:#eef2ff;color:#6366f1;">Platform mapping</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.85rem;">
        <thead style="background:#f8fafc;">
          <tr style="font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;">
            <th class="border-0 py-3 pl-4">Ref</th><th class="border-0 py-3">Category</th>
            <th class="border-0 py-3">Covered by</th><th class="border-0 py-3 text-center pr-4">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($owasp as [$ref,$cat,$tools,$cov]): [$ct,$cc,$cbg] = $cov_style[$cov]; ?>
          <tr>
            <td class="pl-4"><code style="color:#6366f1;font-weight:700;"><?= $ref ?></code></td>
            <td style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($cat) ?></td>
            <td><?php foreach ($tools as $t): ?><span class="badge badge-light border mr-1" style="font-size:.66rem;"><?= htmlspecialchars($t) ?></span><?php endforeach; ?></td>
            <td class="text-center pr-4"><span class="badge" style="background:<?=$cbg?>;color:<?=$cc?>;font-size:.68rem;"><?= $ct ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Standards -->
<div class="card">
  <div class="card-header"><span class="card-title"><i class="fas fa-clipboard-check mr-2" style="color:var(--asf-indigo);"></i>Standards &amp; Frameworks</span></div>
  <div class="card-body">
    <div class="row">
      <?php foreach ($standards as [$name,$icon,$desc]): ?>
      <div class="col-md-6 mb-3">
        <div class="d-flex align-items-start p-3 border rounded h-100">
          <div style="width:40px;height:40px;border-radius:.6rem;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin-right:.75rem;flex-shrink:0;"><i class="fas <?=$icon?>" style="color:#6366f1;"></i></div>
          <div><div style="font-weight:700;color:#1e293b;font-size:.9rem;"><?= htmlspecialchars($name) ?></div>
               <div class="text-muted" style="font-size:.76rem;line-height:1.45;"><?= htmlspecialchars($desc) ?></div></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i>Coverage reflects automated tooling. "Partial" / "Manual" items require analyst review to fully satisfy the control.</small>
  </div>
</div>

<?php require_once '../views/partials/footer.php'; ?>
