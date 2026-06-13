<?php
require_once '../src/auth.php';
require_once '../src/helpers.php';
require_auth();

$page_title = 'Findings Review';
$can_edit = in_array($_SESSION['user_role'] ?? '', ['admin','manager','analyst']);
$flash = null;

// ── Update a finding's status ──────────────────────────────────────
if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
    $id = (int)($_POST['id'] ?? 0);
    $st = $_POST['status'] ?? '';
    if (in_array($st, ['open','in_progress','resolved','wont_fix'], true)) {
        try {
            Database::getInstance()->prepare('UPDATE findings SET status=? WHERE id=?')->execute([$st, $id]);
            asf_audit('finding.status', "id=$id status=$st");
            $flash = ['success', 'Finding updated.'];
        } catch (Throwable $e) { $flash = ['danger', $e->getMessage()]; }
    }
}

$fSev = trim($_GET['severity'] ?? '');
$fSt  = trim($_GET['status'] ?? '');

$rows = []; $db_error = null;
try {
    $pdo = Database::getInstance();
    $sql = "SELECT f.*, j.target, j.scan_types
              FROM findings f LEFT JOIN scan_jobs j ON j.id = f.scan_job_id
             WHERE 1=1";
    $args = [];
    if (in_array($fSev, ['critical','high','medium','low','info'], true))         { $sql .= " AND f.severity=?"; $args[]=$fSev; }
    if (in_array($fSt, ['open','in_progress','resolved','wont_fix'], true))        { $sql .= " AND f.status=?";   $args[]=$fSt;  }
    $sql .= " ORDER BY FIELD(f.severity,'critical','high','medium','low','info'), f.created_at DESC LIMIT 500";
    $stmt = $pdo->prepare($sql); $stmt->execute($args);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $db_error = $e->getMessage(); }

$sevc = ['critical'=>'#b91c1c','high'=>'#ea580c','medium'=>'#d97706','low'=>'#2563eb','info'=>'#64748b'];
$stc  = ['open'=>['Open','#dc2626','#fee2e2'],'in_progress'=>['In Progress','#d97706','#fef3c7'],'resolved'=>['Resolved','#16a34a','#dcfce7'],'wont_fix'=>["Won't Fix",'#64748b','#f1f5f9']];
?>
<?php require_once '../views/partials/header.php'; ?>

<?php if ($db_error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($db_error) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="alert alert-<?= $flash[0] ?>"><i class="fas fa-info-circle mr-2"></i><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
    <span class="card-title"><i class="fas fa-search-plus mr-2" style="color:var(--asf-indigo);"></i>Findings</span>
    <form class="form-inline" method="get" style="gap:.4rem;">
      <select name="severity" class="form-control form-control-sm" style="font-size:.78rem;border-radius:.5rem;" onchange="this.form.submit()">
        <option value="">All severities</option>
        <?php foreach (['critical','high','medium','low','info'] as $s): ?><option value="<?=$s?>" <?=$fSev===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach; ?>
      </select>
      <select name="status" class="form-control form-control-sm ml-1" style="font-size:.78rem;border-radius:.5rem;" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach (['open','in_progress','resolved','wont_fix'] as $s): ?><option value="<?=$s?>" <?=$fSt===$s?'selected':''?>><?=$stc[$s][0]?></option><?php endforeach; ?>
      </select>
      <?php if ($fSev||$fSt): ?><a href="review.php" class="btn btn-sm btn-outline-secondary ml-1">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="card-body p-0">
    <?php if (empty($rows)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-clipboard-check fa-2x d-block mb-2 opacity-50"></i>
      No findings<?= ($fSev||$fSt)?' match the filter':' yet. Run a scan to populate this view' ?>.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.84rem;">
        <thead style="background:#f8fafc;">
          <tr style="font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;">
            <th class="border-0 py-3 pl-4">Sev</th><th class="border-0 py-3">Finding</th>
            <th class="border-0 py-3">Target</th><th class="border-0 py-3">Refs</th>
            <th class="border-0 py-3 pr-4">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $f): [$slabel,$scol,$sbg] = $stc[$f['status']] ?? $stc['open']; ?>
          <tr>
            <td class="pl-4"><span class="badge" style="background:<?=$sevc[$f['severity']]??'#64748b'?>;color:#fff;font-size:.62rem;text-transform:uppercase;"><?= htmlspecialchars($f['severity']) ?></span></td>
            <td style="max-width:420px;">
              <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($f['title']) ?></div>
              <?php if (!empty($f['affected_url'])): ?><div class="text-muted" style="font-size:.68rem;word-break:break-all;"><?= htmlspecialchars($f['affected_url']) ?></div><?php endif; ?>
              <?php if (!empty($f['remediation'])): ?><div style="font-size:.7rem;color:#065f46;"><i class="fas fa-wrench mr-1"></i><?= htmlspecialchars(mb_strimwidth($f['remediation'],0,120,'…')) ?></div><?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($f['target'] ?? '—') ?></td>
            <td style="font-size:.72rem;color:#475569;"><?= htmlspecialchars(trim(($f['cwe_id']??'').' '.($f['cve_id']??''))) ?: '—' ?><?php if(!empty($f['cvss_score'])):?><div class="text-muted">CVSS <?=htmlspecialchars($f['cvss_score'])?></div><?php endif;?></td>
            <td class="pr-4">
              <?php if ($can_edit): ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                <select name="status" class="form-control form-control-sm" style="width:auto;font-size:.72rem;border-radius:.5rem;color:<?=$scol?>;" onchange="this.form.submit()">
                  <?php foreach ($stc as $k=>$v): ?><option value="<?=$k?>" <?=$f['status']===$k?'selected':''?>><?=$v[0]?></option><?php endforeach; ?>
                </select>
              </form>
              <?php else: ?><span class="badge" style="background:<?=$sbg?>;color:<?=$scol?>;font-size:.66rem;"><?= $slabel ?></span><?php endif; ?>
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
