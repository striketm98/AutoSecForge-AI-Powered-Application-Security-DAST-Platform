<?php
require_once '../src/auth.php';
require_auth();

if (!in_array($_SESSION['user_role'] ?? '', ['admin','manager','auditor'])) {
    http_response_code(403); exit('Access denied.');
}

$page_title = 'Audit Log';

$rows = []; $db_error = null; $actions = [];
$fAction = trim($_GET['action'] ?? '');
$fUser   = trim($_GET['user'] ?? '');

try {
    $pdo = Database::getInstance();

    $actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $sql = "SELECT a.*, u.full_name, u.email
              FROM audit_log a
              LEFT JOIN users u ON u.id = a.user_id
             WHERE 1=1";
    $args = [];
    if ($fAction !== '') { $sql .= " AND a.action = ?";              $args[] = $fAction; }
    if ($fUser   !== '') { $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)"; $args[] = "%$fUser%"; $args[] = "%$fUser%"; }
    $sql .= " ORDER BY a.created_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $db_error = $e->getMessage(); }

/** action → [icon,color] */
function audit_style(string $a): array {
    if (str_starts_with($a, 'auth.login_failed')) return ['fa-user-lock', '#dc2626'];
    if (str_starts_with($a, 'auth'))              return ['fa-right-to-bracket', '#16a34a'];
    if (str_starts_with($a, 'scan'))              return ['fa-bolt', '#6366f1'];
    if (str_starts_with($a, 'user'))              return ['fa-user-gear', '#0ea5e9'];
    if (str_starts_with($a, 'client'))            return ['fa-users', '#8b5cf6'];
    return ['fa-circle-info', '#64748b'];
}
?>
<?php require_once '../views/partials/header.php'; ?>

<?php if ($db_error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($db_error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
    <span class="card-title"><i class="fas fa-shield-alt mr-2" style="color:var(--asf-indigo);"></i>Audit Trail</span>
    <form class="form-inline" method="get" style="gap:.4rem;">
      <select name="action" class="form-control form-control-sm" style="font-size:.78rem;border-radius:.5rem;" onchange="this.form.submit()">
        <option value="">All actions</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= htmlspecialchars($a) ?>" <?= $fAction===$a?'selected':'' ?>><?= htmlspecialchars($a) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="user" value="<?= htmlspecialchars($fUser) ?>" placeholder="user…" class="form-control form-control-sm ml-1" style="font-size:.78rem;border-radius:.5rem;">
      <button class="btn btn-sm btn-asf ml-1"><i class="fas fa-filter"></i></button>
      <?php if ($fAction||$fUser): ?><a href="audit.php" class="btn btn-sm btn-outline-secondary ml-1">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="card-body p-0">
    <?php if (empty($rows)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-clipboard-list fa-2x d-block mb-2 opacity-50"></i>
      No audit entries<?= ($fAction||$fUser)?' match the filter':' yet' ?>.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0" style="font-size:.82rem;">
        <thead style="background:#f8fafc;">
          <tr style="font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;">
            <th class="border-0 py-2 pl-4">Time (UTC)</th>
            <th class="border-0 py-2">Action</th>
            <th class="border-0 py-2">User</th>
            <th class="border-0 py-2">Detail</th>
            <th class="border-0 py-2 pr-4">IP</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): [$ic,$col] = audit_style($r['action']); ?>
          <tr>
            <td class="pl-4 text-muted" style="white-space:nowrap;font-size:.75rem;"><?= htmlspecialchars(substr($r['created_at'] ?? '',0,19)) ?></td>
            <td><i class="fas <?=$ic?> mr-1" style="color:<?=$col?>;font-size:.72rem;"></i><code style="font-size:.72rem;color:<?=$col?>;"><?= htmlspecialchars($r['action']) ?></code></td>
            <td><?= htmlspecialchars($r['full_name'] ?? '—') ?><?php if(!empty($r['email'])): ?><div class="text-muted" style="font-size:.66rem;"><?= htmlspecialchars($r['email']) ?></div><?php endif; ?></td>
            <td class="text-muted" style="font-size:.74rem;word-break:break-all;max-width:380px;"><?= htmlspecialchars($r['detail'] ?? '') ?></td>
            <td class="pr-4 text-muted" style="font-size:.72rem;white-space:nowrap;"><?= htmlspecialchars($r['ip_address'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="text-muted text-center py-2" style="font-size:.7rem;">Showing latest <?= count($rows) ?> entries (max 500).</div>
    <?php endif; ?>
  </div>
</div>

<?php require_once '../views/partials/footer.php'; ?>
