<?php require 'db.php'; require 'auth.php'; require_login(); require 'header.php';

$u = $_SESSION['user'];
if ($u['role'] === 'customer') {
  $stmt = $pdo->prepare("SELECT b.*, r.number, r.type, r.rate_cents
                         FROM bookings b JOIN rooms r ON b.room_id=r.id
                         WHERE b.user_id=? ORDER BY b.created_at DESC");
  $stmt->execute([$u['id']]);
} else {
  $stmt = $pdo->query("SELECT b.*, r.number, r.type, r.rate_cents, u.name as customer_name
                       FROM bookings b
                       JOIN rooms r ON b.room_id=r.id
                       JOIN users u ON b.user_id=u.id
                       ORDER BY b.created_at DESC");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $bid = (int)($_POST['booking_id'] ?? 0);
  $action = $_POST['action'] ?? '';
  if ($action === 'cancel') {
    $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND user_id=? AND check_in > CURDATE()")
        ->execute([$bid, $u['id']]);
  } elseif (in_array($u['role'], ['staff','admin']) && in_array($action, ['pending','confirmed','cancelled'])) {
    $pdo->prepare("UPDATE bookings SET status=? WHERE id=?")->execute([$action, $bid]);
  }
  header("Location: my_bookings.php"); exit;
}
?>
<div class="card">
  <h2><?= $u['role']==='customer' ? 'My Bookings' : 'All Bookings' ?></h2>
  <table class="table">
    <tr>
      <?php if ($u['role']!=='customer'): ?><th>Customer</th><?php endif; ?>
      <th>Room</th><th>Type</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Actions</th>
    </tr>
    <?php foreach($rows as $b): ?>
      <tr>
        <?php if ($u['role']!=='customer'): ?><td><?= htmlspecialchars($b['customer_name'] ?? '') ?></td><?php endif; ?>
        <td><?= htmlspecialchars($b['number']) ?></td>
        <td><?= htmlspecialchars($b['type']) ?></td>
        <td><?= htmlspecialchars($b['check_in']) ?></td>
        <td><?= htmlspecialchars($b['check_out']) ?></td>
        <td><?= htmlspecialchars($b['status']) ?></td>
        <td>
          <?php if ($u['role']==='customer'): ?>
            <?php if ($b['status']!=='cancelled' && strtotime($b['check_in']) > time()): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                <button class="btn secondary" name="action" value="cancel">Cancel</button>
              </form>
            <?php endif; ?>
          <?php else: ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
              <select name="action">
                <option value="pending" <?= $b['status']==='pending'?'selected':'' ?>>pending</option>
                <option value="confirmed" <?= $b['status']==='confirmed'?'selected':'' ?>>confirmed</option>
                <option value="cancelled" <?= $b['status']==='cancelled'?'selected':'' ?>>cancelled</option>
              </select>
              <button class="btn secondary">Update</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php require 'footer.php'; ?>
