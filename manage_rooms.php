<?php
// manage_rooms.php — Full Admin control of Rooms + User Bookings
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_role(['admin','staff']);
require_once __DIR__.'/header.php';

// Helper functions
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money($cents){ return '$'.number_format($cents/100, 2); }

// Handle booking actions
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['booking_action'])) {
  $action = $_POST['booking_action'];
  $bid    = (int)($_POST['booking_id'] ?? 0);

  if ($bid) {
    if ($action==='cancel') {
      $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$bid]);
    } elseif ($action==='confirm') {
      $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=?")->execute([$bid]);
    } elseif ($action==='delete') {
      $pdo->prepare("DELETE FROM bookings WHERE id=?")->execute([$bid]);
    }
  }
  header("Location: manage_rooms.php"); exit;
}

// Fetch all bookings with user and room info
$sql = "
  SELECT b.id AS booking_id, b.status, b.check_in, b.check_out, b.created_at, 
         u.name AS user_name, u.email AS user_email, 
         r.type AS room_type, r.number AS room_number, r.rate_cents, r.floor
  FROM bookings b
  JOIN users u ON b.user_id = u.id
  JOIN rooms r ON b.room_id = r.id
  ORDER BY b.created_at DESC
";
$bookings = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Fetch rooms
$rooms = $pdo->query("SELECT id, type, number, inventory, rate_cents, max_guests, is_active FROM rooms ORDER BY type, number")->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="container">
  <h2 class="h2" style="margin-bottom:10px">🏨 Rooms Overview</h2>
  <div class="card">
    <table class="table">
      <thead>
        <tr>
          <th>Room #</th>
          <th>Type</th>
          <th>Rate</th>
          <th>Max Guests</th>
          <th>Inventory</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rooms as $r): ?>
        <tr>
          <td><?= (int)$r['number'] ?></td>
          <td><?= h($r['type']) ?></td>
          <td><?= money($r['rate_cents']) ?></td>
          <td><?= (int)$r['max_guests'] ?></td>
          <td><?= (int)$r['inventory'] ?></td>
          <td><?= $r['is_active'] ? '✅ Active' : '❌ Inactive' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2 class="h2" style="margin:30px 0 10px">📅 All Bookings</h2>
  <p class="muted" style="margin-bottom:10px">View all user bookings, their dates, and manage them.</p>
  <div class="card" style="overflow:auto">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>User</th>
          <th>Email</th>
          <th>Room</th>
          <th>Type</th>
          <th>Check-In</th>
          <th>Check-Out</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td>#<?= (int)$b['booking_id'] ?></td>
            <td><?= h($b['user_name']) ?></td>
            <td><?= h($b['user_email']) ?></td>
            <td><?= (int)$b['room_number'] ?></td>
            <td><?= h($b['room_type']) ?></td>
            <td><?= h($b['check_in']) ?></td>
            <td><?= h($b['check_out']) ?></td>
            <td>
              <?php if ($b['status']==='cancelled'): ?>
                <span class="chip" style="background:#ffe5e5;border-color:#f0bcbc">Cancelled</span>
              <?php elseif ($b['status']==='pending'): ?>
                <span class="chip" style="background:#fff8dc;border-color:#f7d488">Pending</span>
              <?php else: ?>
                <span class="chip" style="background:#e6ffe6;border-color:#9fe69f">Confirmed</span>
              <?php endif; ?>
            </td>
            <td class="tiny"><?= h($b['created_at']) ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="booking_action" value="confirm">
                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                <button class="btn tiny">Confirm</button>
              </form>

              <form method="post" style="display:inline" onsubmit="return confirm('Cancel this booking?');">
                <input type="hidden" name="booking_action" value="cancel">
                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                <button class="btn tiny">Cancel</button>
              </form>

              <form method="post" style="display:inline" onsubmit="return confirm('Delete this booking permanently?');">
                <input type="hidden" name="booking_action" value="delete">
                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                <button class="btn tiny">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$bookings): ?>
          <tr><td colspan="10" class="muted">No bookings found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require_once __DIR__.'/footer.php'; ?>