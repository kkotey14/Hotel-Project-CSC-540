<?php
require 'db.php'; require 'auth.php'; require_login();
if (!in_array($_SESSION['user']['role'], ['admin','staff'])) { header("Location: index.php"); exit; }
require 'header.php';

$q = trim($_GET['q'] ?? '');
$params = []; $where = '';
if ($q !== '') {
  $where = "WHERE (u.name LIKE :q OR u.email LIKE :q)";
  $params[':q'] = "%$q%";
}
$rows = $pdo->prepare("SELECT u.id,u.name,u.email,u.role,u.address,u.date_of_birth,
                              (SELECT MAX(login_at) FROM user_sessions s WHERE s.user_id=u.id) AS last_login,
                              (SELECT MAX(logout_at) FROM user_sessions s WHERE s.user_id=u.id) AS last_logout,
                              (SELECT MAX(created_at) FROM password_resets r WHERE r.user_id=u.id) AS last_pw_reset
                       FROM users u $where ORDER BY u.name ASC LIMIT 200");
$rows->execute($params);
$users = $rows->fetchAll(PDO::FETCH_ASSOC);

// privacy: staff can’t see address/DOB
$canSeePII = ($_SESSION['user']['role'] === 'admin');
?>
<section class="container">
  <div class="card" style="padding:20px">
    <div class="flex" style="justify-content:space-between;align-items:center">
      <h1 class="h2" style="margin:0">Users</h1>
      <form method="get">
        <input class="input" name="q" placeholder="Search name or email…" value="<?= htmlspecialchars($q) ?>">
      </form>
    </div>
    <div class="table-wrap" style="overflow:auto;margin-top:12px">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th><th>Name</th><th>Email</th><th>Role</th>
            <?php if ($canSeePII): ?><th>Address</th><th>DOB</th><?php endif; ?>
            <th>Last login</th><th>Last logout</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>#<?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['role']) ?></td>
            <?php if ($canSeePII): ?>
              <td><?= htmlspecialchars($u['address'] ?? '') ?></td>
              <td><?= htmlspecialchars($u['date_of_birth'] ?? '') ?></td>
            <?php endif; ?>
            <td><?= htmlspecialchars($u['last_login'] ?? '—') ?></td>
            <td><?= htmlspecialchars($u['last_logout'] ?? '—') ?></td>
            <td><a class="btn" href="admin_user_view.php?id=<?= (int)$u['id'] ?>">View</a></td>
          </tr>
          <?php endforeach; if (!$users): ?>
          <tr><td colspan="10" class="muted">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php require 'footer.php'; ?>