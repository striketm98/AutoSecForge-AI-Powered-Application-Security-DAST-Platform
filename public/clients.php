<?php
require_once '../src/auth.php';
require_once '../src/helpers.php';
require_auth();

if (!in_array($_SESSION['user_role'] ?? '', ['admin','manager'])) {
    http_response_code(403); exit('Access denied.');
}

$page_title = 'Clients';
$flash = null; $newPw = null;

// ── Mutations (add / delete) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        $pdo = Database::getInstance();

        if ($action === 'add') {
            $name    = trim($_POST['full_name'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $company = trim($_POST['company'] ?? '');
            $phone   = trim($_POST['phone'] ?? '');
            $notes   = trim($_POST['notes'] ?? '');

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $flash = ['danger', 'A name and a valid email are required.'];
            } else {
                $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $exists->execute([$email]);
                if ($exists->fetch()) {
                    $flash = ['danger', 'A user with that email already exists.'];
                } else {
                    $newPw = bin2hex(random_bytes(5)) . '!Aa';
                    $hash  = password_hash($newPw, PASSWORD_BCRYPT);
                    $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?,?,?,'client')")
                        ->execute([$name, $email, $hash]);
                    $uid = $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO clients (user_id, company, phone, notes) VALUES (?,?,?,?)')
                        ->execute([$uid, $company, $phone, $notes]);
                    asf_audit('client.create', "email=$email company=$company");
                    $flash = ['success', "Client \"{$name}\" created."];
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $chk = $pdo->prepare("SELECT email FROM users WHERE id=? AND role='client'");
            $chk->execute([$id]);
            if ($row = $chk->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);  // cascades clients row
                asf_audit('client.delete', "email={$row['email']}");
                $flash = ['success', 'Client removed.'];
            } else {
                $flash = ['danger', 'Client not found.'];
            }
        }
    } catch (Throwable $e) { $flash = ['danger', $e->getMessage()]; }
}

// ── List ───────────────────────────────────────────────────────────
$clients = []; $db_error = null;
try {
    $pdo = Database::getInstance();
    $clients = $pdo->query(
        "SELECT u.id, u.full_name, u.email, u.created_at,
                c.company, c.phone, c.notes,
                (SELECT COUNT(*) FROM projects p WHERE p.client_id = u.id) AS n_projects
           FROM users u
           LEFT JOIN clients c ON c.user_id = u.id
          WHERE u.role = 'client'
          ORDER BY u.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $db_error = $e->getMessage(); }
?>
<?php require_once '../views/partials/header.php'; ?>

<div id="pageActions">
  <button class="btn btn-asf btn-sm px-3" data-toggle="modal" data-target="#addClient">
    <i class="fas fa-user-plus mr-1"></i>New Client
  </button>
</div>

<?php if ($db_error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($db_error) ?></div>
<?php endif; ?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash[0] ?>"><i class="fas fa-info-circle mr-2"></i><?= htmlspecialchars($flash[1]) ?></div>
<?php endif; ?>
<?php if ($newPw): ?>
<div class="alert alert-warning d-flex align-items-center">
  <i class="fas fa-key mr-2"></i>
  <div>Temporary password (shown once — share securely): <code style="font-size:.9rem;"><?= htmlspecialchars($newPw) ?></code></div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="card-title"><i class="fas fa-users mr-2" style="color:var(--asf-indigo);"></i>Client Accounts</span>
    <span class="badge" style="background:#eef2ff;color:#6366f1;"><?= count($clients) ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($clients)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-users fa-2x d-block mb-2 opacity-50"></i>
      No clients yet. <a href="#" data-toggle="modal" data-target="#addClient">Add one</a>.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.84rem;">
        <thead style="background:#f8fafc;">
          <tr style="font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;">
            <th class="border-0 py-3 pl-4">Client</th>
            <th class="border-0 py-3">Company</th>
            <th class="border-0 py-3">Contact</th>
            <th class="border-0 py-3 text-center">Projects</th>
            <th class="border-0 py-3">Since</th>
            <th class="border-0 py-3 text-right pr-4"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($clients as $c): ?>
          <tr>
            <td class="pl-4">
              <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($c['full_name']) ?></div>
              <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($c['email']) ?></div>
            </td>
            <td><?= htmlspecialchars($c['company'] ?: '—') ?></td>
            <td class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
            <td class="text-center"><span class="badge badge-secondary"><?= (int)$c['n_projects'] ?></span></td>
            <td class="text-muted" style="font-size:.76rem;"><?= htmlspecialchars(substr($c['created_at'] ?? '',0,10)) ?></td>
            <td class="text-right pr-4">
              <form method="post" onsubmit="return confirm('Delete this client account? This cannot be undone.');" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-sm btn-outline-danger px-2 py-1"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add client modal -->
<div class="modal fade" id="addClient" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" style="border-radius:1rem;border:none;">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));border-radius:1rem 1rem 0 0;">
        <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-user-plus mr-2"></i>New Client</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add">
        <div class="form-group"><label class="font-weight-bold" style="font-size:.8rem;">Full name <span class="text-danger">*</span></label>
          <input name="full_name" class="form-control" required></div>
        <div class="form-group"><label class="font-weight-bold" style="font-size:.8rem;">Email <span class="text-danger">*</span></label>
          <input name="email" type="email" class="form-control" required></div>
        <div class="form-row">
          <div class="form-group col-md-6"><label class="font-weight-bold" style="font-size:.8rem;">Company</label>
            <input name="company" class="form-control"></div>
          <div class="form-group col-md-6"><label class="font-weight-bold" style="font-size:.8rem;">Phone</label>
            <input name="phone" class="form-control"></div>
        </div>
        <div class="form-group"><label class="font-weight-bold" style="font-size:.8rem;">Notes</label>
          <textarea name="notes" class="form-control" rows="2"></textarea></div>
        <small class="text-muted">A client login is created with a one-time temporary password.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-sm btn-asf"><i class="fas fa-check mr-1"></i>Create Client</button>
      </div>
    </form>
  </div>
</div>

<?php require_once '../views/partials/footer.php'; ?>
