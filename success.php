<?php
require 'db.php'; require 'auth.php'; require_login(); require 'lib_mail.php'; require 'header.php';

$booking_id = (int)($_GET['booking_id'] ?? 0);

$pdo->prepare("UPDATE payments SET status='paid' WHERE booking_id=?")->execute([$booking_id]);
$pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=?")->execute([$booking_id]);

$info = $pdo->prepare("SELECT b.*, u.email, u.name, r.type, r.number,
  (SELECT amount_cents FROM payments WHERE booking_id=b.id ORDER BY id DESC LIMIT 1) AS amount_cents
  FROM bookings b
  JOIN users u ON b.user_id=u.id
  JOIN rooms r ON b.room_id=r.id
  WHERE b.id=?");
$info->execute([$booking_id]);
$bk = $info->fetch(PDO::FETCH_ASSOC);

if ($bk) {
  $html = "<h2>Booking confirmed</h2>
    <p>Hi ".htmlspecialchars($bk['name']).",</p>
    <p>Your booking is confirmed.</p>
    <ul>
      <li>Room: ".htmlspecialchars($bk['type'])." (".htmlspecialchars($bk['number']).")</li>
      <li>Dates: ".htmlspecialchars($bk['check_in'])." → ".htmlspecialchars($bk['check_out'])."</li>
      <li>Total: $".number_format($bk['amount_cents']/100,2)."</li>
      <li>Status: Confirmed</li>
    </ul>
    <p>See you soon!</p>";
  send_email($bk['email'], "Your HotelApp booking confirmation #{$booking_id}", $html);
}
?>
<section class="card">
  <h2>Payment successful 🎉</h2>
  <p>Your booking is confirmed. A confirmation email has been sent.</p>
  <p><a class="btn" href="my_bookings.php">View My Bookings</a></p>
</section>
<?php require 'footer.php'; ?>
