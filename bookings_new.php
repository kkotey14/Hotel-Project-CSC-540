<?php
// bookings_new.php — booking with inventory & duplicate protection
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/lib_mail.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['confirm'])) {
  header("Location: rooms_list.php"); exit;
}

$user_id = (int)$_SESSION['user']['id'];
$room_id = (int)($_POST['room_id'] ?? 0);
$ci      = trim($_POST['ci'] ?? '');
$co      = trim($_POST['co'] ?? '');
$guests  = max(1, (int)($_POST['guests'] ?? 1));

// Validate room
$st = $pdo->prepare("SELECT * FROM rooms WHERE id=? AND is_active=1");
$st->execute([$room_id]);
$room = $st->fetch(PDO::FETCH_ASSOC);
if (!$room) { header("Location: rooms_list.php"); exit; }

// Validate dates
function valid_date($d) {
  $t = DateTime::createFromFormat('Y-m-d', $d);
  return $t && $t->format('Y-m-d') === $d;
}
if (!valid_date($ci) || !valid_date($co) || $ci >= $co) {
  header("Location: rooms_list.php"); exit;
}

// ✅ Prevent duplicate bookings by same user for same room type and date
$dupe = $pdo->prepare("
  SELECT COUNT(*) FROM bookings
  WHERE user_id = ? AND room_id = ?
    AND status IN ('pending','confirmed')
    AND NOT (check_out <= ? OR check_in >= ?)
");
$dupe->execute([$user_id, $room_id, $ci, $co]);
if ($dupe->fetchColumn() > 0) {
  require 'header.php';
  echo "<section class='container'><div class='card' style='max-width:700px;margin:0 auto;padding:22px'>
          <h2>You already have a booking</h2>
          <p>You’ve already booked this room for overlapping dates. 
          You can extend your stay by modifying your reservation in <a href='my_bookings.php'>My Bookings</a>.</p>
          <a class='btn' href='rooms_list.php'>Back to rooms</a>
        </div></section>";
  require 'footer.php';
  exit;
}

// ✅ Check inventory and assign unique unit number (like Room 101 → 102 → 103...)
$inventory = max(1, (int)$room['inventory']);
$booked = $pdo->prepare("
  SELECT unit_number FROM bookings
   WHERE room_id = ?
     AND status IN ('pending','confirmed')
     AND NOT (check_out <= ? OR check_in >= ?)
");
$booked->execute([$room_id, $ci, $co]);
$taken = $booked->fetchAll(PDO::FETCH_COLUMN, 0);
$taken = array_map('intval', $taken);

$unit_number = null;
for ($i = 1; $i <= $inventory; $i++) {
  if (!in_array($i, $taken, true)) {
    $unit_number = $i;
    break;
  }
}

if ($unit_number === null) {
  require 'header.php';
  echo "<section class='container'><div class='card' style='padding:22px;max-width:700px;margin:0 auto'>
          <h2>No Availability Left</h2>
          <p>All rooms of this type are fully booked for your selected dates. Please try another type or date range.</p>
          <a class='btn' href='rooms_list.php'>Back to rooms</a>
        </div></section>";
  require 'footer.php';
  exit;
}

// ✅ Create the booking
$pdo->beginTransaction();
try {
  $ins = $pdo->prepare("
    INSERT INTO bookings (user_id, room_id, check_in, check_out, status, unit_number)
    VALUES (?, ?, ?, ?, 'confirmed', ?)
  ");
  $ins->execute([$user_id, $room_id, $ci, $co, $unit_number]);
  $booking_id = $pdo->lastInsertId();
  $pdo->commit();
} catch (Exception $e) {
  $pdo->rollBack();
  die("Error creating booking: " . $e->getMessage());
}

// ✅ Send confirmation email (with assigned room number)
try {
  $ust = $pdo->prepare("SELECT name, email FROM users WHERE id=?");
  $ust->execute([$user_id]);
  $u = $ust->fetch(PDO::FETCH_ASSOC);

  if ($u && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
    $hotel_name  = defined('HOTEL_NAME') ? HOTEL_NAME : 'Our Hotel';
    $nights = (new DateTime($ci))->diff(new DateTime($co))->days;
    $nights = max(1, $nights);
    $total_cents = $nights * (int)$room['rate_cents'];

    $html = "
    <h2>{$hotel_name} — Booking Confirmed</h2>
    <p>Hi ".htmlspecialchars($u['name']).", your reservation is confirmed.</p>
    <ul>
      <li><b>Booking #:</b> #{$booking_id}</li>
      <li><b>Room Type:</b> {$room['type']}</li>
      <li><b>Assigned Room #:</b> {$unit_number}</li>
      <li><b>Check-in:</b> {$ci}</li>
      <li><b>Check-out:</b> {$co}</li>
      <li><b>Guests:</b> {$guests}</li>
      <li><b>Estimated Total:</b> $".number_format($total_cents/100,2)."</li>
    </ul>
    <a href='my_bookings.php'>View My Bookings</a>
    ";

    send_mail($u['email'], "Booking Confirmed — {$room['type']} Room #{$unit_number}", $html);
  }
} catch (Exception $e) {
  error_log('Email failed: '.$e->getMessage());
}

// ✅ Confirmation Page
require 'header.php';
echo "
<section class='container'>
  <div class='card' style='max-width:700px;margin:0 auto;padding:22px'>
    <h2>Reservation Confirmed!</h2>
    <p>Your reservation has been successfully created. Here are your details:</p>
    <table class='table' style='width:100%;border-collapse:collapse'>
      <tr><td>Booking #</td><td>#{$booking_id}</td></tr>
      <tr><td>Room Type</td><td>{$room['type']}</td></tr>
      <tr><td>Assigned Room #</td><td>{$unit_number}</td></tr>
      <tr><td>Check-in</td><td>{$ci}</td></tr>
      <tr><td>Check-out</td><td>{$co}</td></tr>
    </table>
    <div style='margin-top:12px;display:flex;gap:10px'>
      <a class='btn' href='my_bookings.php'>View My Bookings</a>
      <a class='btn' href='rooms_list.php'>Back to Rooms</a>
    </div>
  </div>
</section>";
require 'footer.php';
?>