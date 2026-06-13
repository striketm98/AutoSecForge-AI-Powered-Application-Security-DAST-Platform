<?php
require_once '../src/auth.php';
require_auth();
$page_title = 'Deliverables';

$rows = []; $db_error = null;
$tot = ['reports'=>0,'findings'=>0,'critical'=>0,'high'=>0];
try {
    $pdo  = Database::getInstance();
    $rows = $pdo->query(
        "SELECT j.id, j.target, j.scan_types, j.status, j.model, j.created_at,
                u.full_name AS analyst,
                COUNT(f.id)                                            AS n_findings,
                SUM(f.severity='critical')                            AS n_crit,
                SUM(f.severity='high')                                AS n_high,
                SUM(f.severity='medium')                              AS n_med,
                SUM(f.severity='low')                                 AS n_low
           FROM scan_jobs j
           LEFT JOIN users u    ON u.id = j.triggered_by
           LEFT JOIN findings f ON f.scan_job_id = j.id
          WHERE j.status IN ('completed','partial')
          GROUP BY j.id
          ORDER BY j.created_at DESC
          LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $tot['reports']++;
        $tot['findings'] += (int)$r['n_findings'];
        $tot['critical'] += (int)$r['n_crit'];
        $tot['high']     += (int)$r['n_high'];
    }
} catch (Throwable $e) { $db_error = $e->getMessage(); }

/** Worst severity → [label,color] */
function deliv_risk(array $r): array {
    if ((int)$r['n_crit'] > 0) return ['Critical','#b91c1c'];
    if ((int)$r['n_high'] > 0) return ['High','#ea580c'];
    if ((int)$r['n_med']  > 0) return ['Medium','#d97706'];
    if ((int)$r['n_low']  > 0) return ['Low','#2563eb'];
    return ['Info','#64748b'];
}
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

<!-- Summary cards -->
<div class="row">
  <?php
  $cards = [
    ['Report Deliverables', $tot['reports'],  'fa-box-open',       '#6366f1'],
    ['Total Findings',      $tot['findings'], 'fa-bug',            '#0ea5e9'],
    ['Critical',            $tot['critical'], 'fa-radiation',      '#b91c1c'],
    ['High',                $tot['high'],     'fa-exclamation-triangle', '#ea580c'],
  ];
  foreach ($cards as [$label,$val,$icon,$col]): ?>
  <div class="col-6 col-lg-3 mb-3">
    <div class="card h-100" style="border:1px solid #f1f5f9 !important;">
      <div class="card-body d-flex align-items-center">
        <div style="width:46px;height:46px;border-radius:.75rem;background:<?=$col?>1a;display:flex;align-items:center;justify-content:center;margin-right:.85rem;flex-shrink:0;">
          <i class="fas <?=$icon?>" style="color:<?=$col?>;"></i>
        </div>
        <div>
          <div style="font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1;"><?= (int)$val ?></div>
          <div class="text-muted" style="font-size:.74rem;"><?= $label ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="card-title"><i class="fas fa-box-open mr-2" style="color:var(--asf-indigo);"></i>Client Deliverables</span>
    <span class="badge" style="background:#eef2ff;color:#6366f1;"><?= count($rows) ?> report<?= count($rows)!=1?'s':'' ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($rows)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-box-open fa-2x d-block mb-2 opacity-50"></i>
      No deliverables yet. <a href="scan_trigger.php">Run a security review</a> to generate one.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.84rem;">
        <thead style="background:#f8fafc;">
          <tr style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;">
            <th class="border-0 py-3 pl-4">Report</th>
            <th class="border-0 py-3">Target</th>
            <th class="border-0 py-3 text-center">Risk</th>
            <th class="border-0 py-3 text-center">Findings</th>
            <th class="border-0 py-3">Date</th>
            <th class="border-0 py-3 text-right pr-4">Download</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          [$risk,$rc] = deliv_risk($r);
          $rid = 'ASF-' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT);
        ?>
          <tr>
            <td class="pl-4"><code style="font-size:.74rem;color:#6366f1;font-weight:600;"><?= $rid ?></code></td>
            <td><span style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($r['target']) ?></span>
                <div class="text-muted" style="font-size:.68rem;"><?= htmlspecialchars($r['scan_types']) ?></div></td>
            <td class="text-center"><span class="badge" style="background:<?=$rc?>;color:#fff;font-size:.66rem;"><?= $risk ?></span></td>
            <td class="text-center"><span style="font-weight:700;color:#1e293b;"><?= (int)$r['n_findings'] ?></span></td>
            <td class="text-muted" style="font-size:.76rem;"><?= htmlspecialchars(substr($r['created_at'] ?? '',0,16)) ?></td>
            <td class="text-right pr-4">
              <div class="btn-group btn-group-sm">
                <a href="report.php?export=<?=(int)$r['id']?>&format=pdf"  class="btn btn-outline-danger"  title="PDF"><i class="fas fa-file-pdf"></i></a>
                <a href="report.php?export=<?=(int)$r['id']?>&format=doc"  class="btn btn-outline-primary" title="Word"><i class="fas fa-file-word"></i></a>
                <a href="report.php?export=<?=(int)$r['id']?>&format=html" target="_blank" class="btn btn-outline-info" title="Preview"><i class="fas fa-eye"></i></a>
                <a href="report.php?export=<?=(int)$r['id']?>&format=txt"  class="btn btn-outline-secondary" title="Text"><i class="fas fa-file-alt"></i></a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once '../views/partials/footer.php'; ?>
