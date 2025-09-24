<?php
require 'db.php'; require 'auth.php'; require_login();

$room_id = (int)($_GET['room_id'] ?? 0);

// Lookup room
$r = $pdo->prepare("SELECT * FROM rooms WHERE id=? AND is_active=1");
$r->execute([$room_id]);
$room = $r->fetch(PDO::FETCH_ASSOC);

if (!$room) {
  require 'header.php';
  echo "<section class='card'><h2>Room not available.</h2><p><a class='btn' href='rooms_list.php'>Back to rooms</a></p></section>";
  require 'footer.php'; exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $check_in  = $_POST['check_in']  ?? '';
  $check_out = $_POST['check_out'] ?? '';
  $guests    = isset($_POST['guests']) ? (int)$_POST['guests'] : 1;

  // Validate dates
  $today = new DateTime('today');
  $ci = DateTime::createFromFormat('Y-m-d', $check_in);
  $co = DateTime::createFromFormat('Y-m-d', $check_out);

  if (!$ci || !$co || $ci >= $co) {
    $err = 'Invalid dates.';
  } elseif ($ci < $today) {
    $err = 'You cannot book dates in the past.';
  } elseif ($guests < 1 || $guests > (int)$room['max_guests']) {
    $err = 'Guest count exceeds room limit.';
  } else {
    // Availability check
    $q = $pdo->prepare("SELECT COUNT(*) FROM bookings
      WHERE room_id=? AND status IN ('pending','confirmed')
        AND ( (check_in < ? AND check_out > ?) OR (check_in >= ? AND check_in < ?) )");
    $q->execute([$room_id, $check_out, $check_in, $check_in, $check_out]);
    $conflict = $q->fetchColumn() > 0;

    if ($conflict) {
      $err = 'Room is not available for those dates.';
    } else {
      try {
        $pdo->beginTransaction();

        // Create booking (include guests)
        $ins = $pdo->prepare("INSERT INTO bookings (user_id,room_id,guests,check_in,check_out,status) VALUES (?,?,?,?,?, 'pending')");
        $ins->execute([$_SESSION['user']['id'], $room_id, $guests, $check_in, $check_out]);
        $booking_id = $pdo->lastInsertId();

        // Compute amount = nights * rate
        $nightStmt = $pdo->prepare("SELECT DATEDIFF(?, ?) AS nights, rate_cents FROM rooms WHERE id=?");
        $nightStmt->execute([$check_out, $check_in, $room_id]);
        $row = $nightStmt->fetch(PDO::FETCH_ASSOC);
        $nights = max(1, (int)$row['nights']);
        $amount_cents = $nights * (int)$row['rate_cents'];

        // Payment row
        $pdo->prepare("INSERT INTO payments (booking_id, amount_cents, status) VALUES (?,?, 'unpaid')")
            ->execute([$booking_id, $amount_cents]);

        $pdo->commit();

        // go to checkout
        header("Location: checkout.php?booking_id=" . $booking_id);
        exit;
      } catch (Exception $e) {
        $pdo->rollBack();
        $err = 'Unexpected error while creating your booking.';
      }
    }
  }
}

// If we reach here, show error and link back
require 'header.php';
?>
<section class="container">
  <div class="card">
    <h2 class="h2">We couldn’t complete your booking</h2>
    <p class="flash error"><?= htmlspecialchars($err ?: 'Unknown error') ?></p>
    <p><a class="btn" href="room.php?id=<?= $room_id ?>">Back to room</a></p>
  </div>
</section>
<?php require 'footer.php'; ?>
