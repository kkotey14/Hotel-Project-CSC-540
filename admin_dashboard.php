<?php require 'db.php'; require 'auth.php'; require_role(['staff','admin']); require 'header.php';
$rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$upcoming = $pdo->query("SELECT COUNT(*) FROM bookings WHERE check_in >= CURDATE() AND status!='cancelled'")->fetchColumn();
?>
<div class="card">
  <h2>Admin Dashboard</h2>
  <p><b>Total Rooms:</b> <?= (int)$rooms ?> | <b>Total Bookings:</b> <?= (int)$bookings ?> | <b>Upcoming check-ins:</b> <?= (int)$upcoming ?></p>
  <p><a class="btn" href="rooms_list.php">Manage Rooms</a> <a class="btn" href="my_bookings.php">Manage Bookings</a></p>
</div>
<?php require 'footer.php'; ?>
