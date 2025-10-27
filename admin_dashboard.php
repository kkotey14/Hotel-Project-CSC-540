<?php
require 'db.php';
require 'auth.php';
require_role(['staff','admin']);
require 'header.php';

// KPIs
$rooms     = (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$bookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$upcoming  = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE check_in >= CURDATE() AND status IN ('pending','confirmed')")->fetchColumn();
$customers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();

// Recent bookings (last 8, newest first)
$recentStmt = $pdo->query("
  SELECT
    b.id,
    b.check_in,
    b.check_out,
    b.status,
    r.number        AS room_number,
    r.type          AS room_type,
    COALESCE(NULLIF(CONCAT(TRIM(IFNULL(u.first_name,'')),' ',TRIM(IFNULL(u.last_name,''))), ' '), u.name, u.email) AS guest_name,
    u.email         AS guest_email
  FROM bookings b
  JOIN rooms r ON r.id = b.room_id
  JOIN users u ON u.id = b.user_id
  ORDER BY b.created_at DESC
  LIMIT 8
");
$recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<section class="container">
  <div class="grid">
    <div class="span-3">
      <div class="card kpi">
        <div class="kpi-num"><?= $rooms ?></div>
        <div class="kpi-label">Rooms</div>
      </div>
    </div>
    <div class="span-3">
      <div class="card kpi">
        <div class="kpi-num"><?= $bookings ?></div>
        <div class="kpi-label">Bookings</div>
      </div>
    </div>
    <div class="span-3">
      <div class="card kpi">
        <div class="kpi-num"><?= $upcoming ?></div>
        <div class="kpi-label">Upcoming</div>
      </div>
    </div>
    <div class="span-3">
      <div class="card kpi">
        <div class="kpi-num"><?= $customers ?></div>
        <div class="kpi-label">Customers</div>
      </div>
    </div>
  </div>

  <div class="card" style="padding:16px;margin-top:14px">
    <h2 class="h3" style="margin:0 0 10px">Quick Links</h2>
    <p>
      <a class="btn" href="rooms_list.php">Manage Rooms</a>
      <!-- point to the new powerful admin list -->
      <a class="btn" href="admin_bookings.php">Manage Bookings</a>
      <a class="btn" href="admin_users.php">Users</a>
    </p>
  </div>

  <div class="card" style="padding:16px;margin-top:14px">
    <h2 class="h3" style="margin:0 0 10px">Recent Bookings</h2>
    <?php if ($recent): ?>
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Guest</th>
            <th>Room</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>Status</th>
            <th>Open</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $b): ?>
            <tr>
              <td><?= (int)$b['id'] ?></td>
              <td>
                <?= h($b['guest_name']) ?>
                <div class="tiny muted"><?= h($b['guest_email']) ?></div>
              </td>
              <td><?= h($b['room_type']).' · '.h($b['room_number']) ?></td>
              <td><?= h($b['check_in']) ?></td>
              <td><?= h($b['check_out']) ?></td>
              <td><?= h($b['status']) ?></td>
              <!-- open the admin list focused on this booking id -->
              <td><a class="btn" href="admin_bookings.php?q=<?= (int)$b['id'] ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="muted" style="margin:0">No recent bookings.</p>
    <?php endif; ?>
  </div>
</section>
<?php require 'footer.php'; ?>