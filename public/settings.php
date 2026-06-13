<?php
require_once '../src/auth.php';
require_once '../src/helpers.php';
require_auth();
$page_title = 'Settings';

$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';
$me       = (int)($_SESSION['user_id'] ?? 0);
$flash = null; $newPw = null;

// ── User-management mutations (admin only) ─────────────────────────
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $roles  = ['admin','manager','analyst','auditor','executive'];
    try {
        $pdo = Database::getInstance();

        if ($action === 'add_user') {
            $name  = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role  = in_array($_POST['role'] ?? '', $roles, true) ? $_POST['role'] : 'analyst';
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $flash = ['danger', 'Name and a valid email are required.'];
            } else {
                $ex = $pdo->prepare('SELECT id FROM users WHERE email=?'); $ex->execute([$email]);
                if ($ex->fetch()) { $flash = ['danger', 'That email is already registered.']; }
                else {
                    $newPw = bin2hex(random_bytes(5)) . '!Aa';
                    $pdo->prepare('INSERT INTO users (full_name,email,password,role) VALUES (?,?,?,?)')
                        ->execute([$name, $email, password_hash($newPw, PASSWORD_BCRYPT), $role]);
                    asf_audit('user.create', "email=$email role=$role");
                    $flash = ['success', "User \"{$name}\" created as {$role}."];
                }
            }
        } elseif ($action === 'set_role') {
            $id = (int)($_POST['id'] ?? 0);
            $role = in_array($_POST['role'] ?? '', $roles, true) ? $_POST['role'] : null;
            if ($id === $me)      { $flash = ['danger', 'You cannot change your own role.']; }
            elseif (!$role)       { $flash = ['danger', 'Invalid role.']; }
            else { $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $id]);
                   asf_audit('user.set_role', "id=$id role=$role"); $flash = ['success', 'Role updated.']; }
        } elseif ($action === 'delete_user') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === $me) { $flash = ['danger', 'You cannot delete your own account.']; }
            else { $r = $pdo->prepare('SELECT email FROM users WHERE id=?'); $r->execute([$id]);
                   if ($row = $r->fetch(PDO::FETCH_ASSOC)) {
                       $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
                       asf_audit('user.delete', "email={$row['email']}"); $flash = ['success', 'User removed.'];
                   } }
        }
    } catch (Throwable $e) { $flash = ['danger', $e->getMessage()]; }
}

// ── Data ───────────────────────────────────────────────────────────
$users = []; $db_error = null;
try {
    $pdo = Database::getInstance();
    $users = $pdo->query("SELECT id, full_name, email, role, created_at FROM users WHERE role <> 'client' ORDER BY FIELD(role,'admin','manager','analyst','auditor','executive'), full_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $db_error = $e->getMessage(); }

$env = @parse_ini_file('/var/www/html/.env', false, INI_SCANNER_RAW) ?: [];
$sys = [
    'Application'   => $env['APP_NAME'] ?? 'AutoSecForge',
    'App URL'       => $env['APP_URL'] ?? '—',
    'Environment'   => $env['APP_ENV'] ?? 'production',
    'AI Model'      => $env['OLLAMA_MODEL'] ?? '—',
    'MCP Router'    => $env['MCP_URL'] ?? '—',
    'AI Agent'      => $env['AI_AGENT_URL'] ?? '—',
    'PHP Version'   => PHP_VERSION,
    'Server Time'   => gmdate('Y-m-d H:i') . ' UTC',
];
$role_color = ['admin'=>'#b91c1c','manager'=>'#7c3aed','analyst'=>'#2563eb','auditor'=>'#0891b2','executive'=>'#ca8a04'];
?>
<?php require_once '../views/partials/header.php'; ?>

<?php if ($db_error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($db_error) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="alert alert-<?= $flash[0] ?>"><i class="fas fa-info-circle mr-2"></i><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>
<?php if ($newPw): ?><div class="alert alert-warning"><i class="fas fa-key mr-2"></i>Temporary password (shown once): <code style="font-size:.9rem;"><?= htmlspecialchars($newPw) ?></code></div><?php endif; ?>

<div class="row">
  <!-- Profile + system -->
  <div class="col-lg-4 mb-4">
    <div class="card mb-3">
      <div class="card-header card-header-gradient"><span class="card-title text-white"><i class="fas fa-user-circle mr-2"></i>My Profile</span></div>
      <div class="card-body">
        <table class="table table-sm mb-2" style="font-size:.84rem;">
          <tr><td class="text-muted pl-0">Name</td><td class="text-right"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></td></tr>
          <tr><td class="text-muted pl-0">Email</td><td class="text-right"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></td></tr>
          <tr><td class="text-muted pl-0">Role</td><td class="text-right"><span class="badge" style="background:<?= $role_color[$_SESSION['user_role']??'']??'#64748b' ?>;color:#fff;"><?= htmlspecialchars($_SESSION['user_role'] ?? '') ?></span></td></tr>
        </table>
        <a href="change_password.php" class="btn btn-sm btn-outline-primary btn-block"><i class="fas fa-key mr-1"></i>Change Password</a>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-server mr-2" style="color:var(--asf-indigo);"></i>System</span></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:.8rem;">
          <?php foreach ($sys as $k=>$v): ?>
          <tr><td class="text-muted pl-3"><?= htmlspecialchars($k) ?></td><td class="text-right pr-3"><code style="font-size:.74rem;color:#334155;"><?= htmlspecialchars($v) ?></code></td></tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  </div>

  <!-- User management -->
  <div class="col-lg-8 mb-4">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title"><i class="fas fa-users-cog mr-2" style="color:var(--asf-indigo);"></i>Team &amp; Access</span>
        <?php if ($is_admin): ?>
        <button class="btn btn-sm btn-asf" data-toggle="modal" data-target="#addUser"><i class="fas fa-user-plus mr-1"></i>Add User</button>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0" style="font-size:.84rem;">
            <thead style="background:#f8fafc;">
              <tr style="font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;">
                <th class="border-0 py-3 pl-4">User</th><th class="border-0 py-3">Role</th>
                <th class="border-0 py-3">Since</th><th class="border-0 py-3 text-right pr-4"></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td class="pl-4"><div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($u['full_name']) ?><?php if($u['id']==$me):?> <span class="badge badge-light">you</span><?php endif;?></div>
                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($u['email']) ?></div></td>
                <td>
                  <?php if ($is_admin && $u['id'] != $me): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="set_role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <select name="role" class="form-control form-control-sm d-inline-block" style="width:auto;font-size:.74rem;border-radius:.5rem;" onchange="this.form.submit()">
                      <?php foreach (['admin','manager','analyst','auditor','executive'] as $r): ?>
                        <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= $r ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                  <?php else: ?>
                  <span class="badge" style="background:<?= $role_color[$u['role']]??'#64748b' ?>;color:#fff;"><?= htmlspecialchars($u['role']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-muted" style="font-size:.76rem;"><?= htmlspecialchars(substr($u['created_at'] ?? '',0,10)) ?></td>
                <td class="text-right pr-4">
                  <?php if ($is_admin && $u['id'] != $me): ?>
                  <form method="post" onsubmit="return confirm('Delete this user?');" style="display:inline;">
                    <input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger px-2 py-1"><i class="fas fa-trash"></i></button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php if (!$is_admin): ?>
    <div class="alert alert-info mt-3" style="font-size:.82rem;"><i class="fas fa-info-circle mr-1"></i>Only administrators can add or modify users.</div>
    <?php endif; ?>
  </div>
</div>

<?php if ($is_admin): ?>
<!-- Add user modal -->
<div class="modal fade" id="addUser" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" style="border-radius:1rem;border:none;">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--asf-indigo),var(--asf-violet));border-radius:1rem 1rem 0 0;">
        <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-user-plus mr-2"></i>Add User</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_user">
        <div class="form-group"><label class="font-weight-bold" style="font-size:.8rem;">Full name <span class="text-danger">*</span></label><input name="full_name" class="form-control" required></div>
        <div class="form-group"><label class="font-weight-bold" style="font-size:.8rem;">Email <span class="text-danger">*</span></label><input name="email" type="email" class="form-control" required></div>
        <div class="form-group"><label class="font-weight-bold" style="font-size:.8rem;">Role</label>
          <select name="role" class="form-control">
            <?php foreach (['analyst','manager','admin','auditor','executive'] as $r): ?><option value="<?= $r ?>"><?= ucfirst($r) ?></option><?php endforeach; ?>
          </select></div>
        <small class="text-muted">A one-time temporary password is generated.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-sm btn-asf"><i class="fas fa-check mr-1"></i>Create User</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once '../views/partials/footer.php'; ?>
