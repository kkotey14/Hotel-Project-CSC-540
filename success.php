<?php
// success.php — Stripe says payment completed; now create booking, record payment, send email.

// Debug (optional; remove when done)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib_mail.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET);

// For absolute links in email (e.g., "View My Bookings")
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base    = $scheme . '://' . $_SERVER['HTTP_HOST'] . (defined('BASE_URL') ? BASE_URL : '');

$session_id = $_GET['session_id'] ?? '';
if (!$session_id) { header("Location: index.php"); exit; }

try {
  // 1) Retrieve and verify Stripe Checkout Session
  $session = \Stripe\Checkout\Session::retrieve($session_id);
  if ($session->payment_status !== 'paid') {
    header("Location: cancel.php"); exit;
  }

  // 2) Pull metadata saved at session creation time
  $md = $session->metadata ? $session->metadata->toArray() : [];
  $user_id    = (int)($md['user_id'] ?? 0);
  $room_id    = (int)($md['room_id'] ?? 0);
  $check_in   = $md['check_in'] ?? '';
  $check_out  = $md['check_out'] ?? '';
  $guests     = (int)($md['guests'] ?? 1);
  $nights     = (int)($md['nights'] ?? 1);
  $rate_cents = (int)($md['rate_cents'] ?? 0);
  $amount_cents = $nights * $rate_cents;

  // 3) Validate room and inputs
  $rstmt = $pdo->prepare("SELECT * FROM rooms WHERE id=? AND is_active=1");
  $rstmt->execute([$room_id]);
  $room = $rstmt->fetch(PDO::FETCH_ASSOC);
  if (!$room) { header("Location: rooms_list.php"); exit; }

  if ($guests > (int)$room['max_guests'] || !$check_in || !$check_out || $check_in >= $check_out) {
    header("Location: rooms_list.php"); exit;
  }

  // 4) Final availability check (avoid rare race conditions)
  $q = $pdo->prepare("SELECT COUNT(*) FROM bookings
    WHERE room_id=? AND status IN ('pending','confirmed')
      AND ( (check_in < ? AND check_out > ?) OR (check_in >= ? AND check_in < ?) )");
  $q->execute([$room_id, $check_out, $check_in, $check_in, $check_out]);
  $conflict = $q->fetchColumn() > 0;
  if ($conflict) {
    require 'header.php';
    echo "<section class='container'><div class='card' style='padding:24px'>
            <h2>Unexpected Conflict</h2>
            <p>The room was just booked by someone else. We’ll contact you to resolve the payment.</p>
          </div></section>";
    require 'footer.php'; exit;
  }

  // 5) Create booking + payment atomically
  $pdo->beginTransaction();

  // Booking
  $insB = $pdo->prepare("INSERT INTO bookings (user_id, room_id, check_in, check_out, status)
                         VALUES (?,?,?,?, 'confirmed')");
  $insB->execute([$user_id, $room_id, $check_in, $check_out]);
  $booking_id = $pdo->lastInsertId();

  // Payment record
  $payment_intent_id = $session->payment_intent ?? null;
  $hasProviderSession = (bool)$pdo->query("SHOW COLUMNS FROM payments LIKE 'provider_session_id'")->fetch();

  if ($hasProviderSession) {
    $insP = $pdo->prepare("INSERT INTO payments
        (booking_id, amount_cents, status, provider, charge_id, provider_session_id)
        VALUES (?, ?, 'paid', 'stripe', ?, ?)");
    $insP->execute([$booking_id, $amount_cents, $payment_intent_id, $session->id]);
  } else {
    $insP = $pdo->prepare("INSERT INTO payments
        (booking_id, amount_cents, status, provider, charge_id)
        VALUES (?, ?, 'paid', 'stripe', ?)");
    $insP->execute([$booking_id, $amount_cents, $payment_intent_id]);
  }

  $pdo->commit();

  // 6) Send confirmation email
  try {
    $ust = $pdo->prepare("SELECT name, email FROM users WHERE id=? LIMIT 1");
    $ust->execute([$user_id]);
    $u = $ust->fetch(PDO::FETCH_ASSOC);

    if ($u && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
      $guest_name  = $u['name'] ?: 'Guest';
      $hotel_name  = defined('HOTEL_NAME')  ? HOTEL_NAME  : 'The Riverside';
      $hotel_email = defined('HOTEL_EMAIL') ? HOTEL_EMAIL : 'reservations@yourhotel.test';
      $hotel_phone = defined('HOTEL_PHONE') ? HOTEL_PHONE : '(203) 555-0123';

      $viewUrl = $base . '/my_bookings.php';

      // Professional HTML Email
      $email_html = '
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Booking Confirmation</title>
<style>
  body { margin:0; background:#f6f7f9; font-family:"Helvetica Neue",Arial,sans-serif; color:#333; }
  .wrap { max-width:680px; margin:24px auto; padding:0 12px; }
  .card { background:#fff; border:1px solid #eaecef; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,.05); }
  .header { padding:24px; background:#1a3c34; color:#fff; text-align:center; }
  .header h1 { margin:0; font-size:22px; font-weight:700; }
  .inner { padding:22px 24px; }
  .table { width:100%; border-collapse:collapse; margin:18px 0; font-size:14px; }
  .table td { border:1px solid #e5e7eb; padding:10px 12px; }
  .table td.label { background:#f9fafb; width:38%; font-weight:600; color:#374151; }
  .btn { display:inline-block; padding:12px 18px; border-radius:6px; background:#3b5d2a; color:#fff !important; text-decoration:none; font-weight:600; margin-top:18px; }
  .footer { text-align:center; color:#6b7280; font-size:12px; padding:18px 8px; border-top:1px solid #eaecef; margin-top:20px; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="header">
        <h1>'.htmlspecialchars($hotel_name).' — Booking Confirmation</h1>
      </div>
      <div class="inner">
        <p>Dear '.htmlspecialchars($guest_name).',</p>
        <p>Thank you for choosing <strong>'.htmlspecialchars($hotel_name).'</strong>. Your reservation is now confirmed:</p>

        <table class="table">
          <tr><td class="label">Booking #</td><td>#'.(int)$booking_id.'</td></tr>
          <tr><td class="label">Room</td><td>'.htmlspecialchars($room['type']).' — Room '.htmlspecialchars($room['number']).'</td></tr>
          <tr><td class="label">Guests</td><td>'.(int)$guests.'</td></tr>
          <tr><td class="label">Check-in</td><td>'.htmlspecialchars($check_in).'</td></tr>
          <tr><td class="label">Check-out</td><td>'.htmlspecialchars($check_out).'</td></tr>
          <tr><td class="label">Total Paid</td><td>$'.number_format($amount_cents/100, 2).'</td></tr>
        </table>

        <p><a class="btn" href="'.htmlspecialchars($viewUrl).'">View My Bookings</a></p>
        <p style="color:#6b7280">Questions? Contact us at <a href="mailto:'.htmlspecialchars($hotel_email).'">'.htmlspecialchars($hotel_email).'</a> or call '.htmlspecialchars($hotel_phone).'.</p>
      </div>
    </div>
    <div class="footer">© '.date('Y').' '.htmlspecialchars($hotel_name).'. All rights reserved.</div>
  </div>
</body>
</html>';

      $subject = 'Booking Confirmed — '.htmlspecialchars($room['type']).' (Room '.htmlspecialchars($room['number']).')';
      send_mail($u['email'], $subject, $email_html);
    }
  } catch (Exception $em) {
    error_log("Email send failed: ".$em->getMessage());
  }

  // 7) Show success page
  require 'header.php';
  ?>
  <section class="container">
    <div class="card" style="padding:24px;max-width:760px;margin:0 auto;">
      <h1 class="h2" style="margin:0 0 10px">Payment Successful — Booking Confirmed</h1>
      <p class="muted">We’ve emailed a confirmation and your details are below.</p>
      <div class="grid">
        <div class="span-7">
          <div class="hero-img-wrap">
            <img src="<?= htmlspecialchars($room['image_url'] ?: 'https://via.placeholder.com/1200x800?text=Room') ?>" alt="Room">
          </div>
        </div>
        <div class="span-5">
          <div class="card" style="padding:16px">
            <div class="h3" style="margin:0 0 6px"><?= htmlspecialchars($room['type']) ?> · Room <?= htmlspecialchars($room['number']) ?></div>
            <div class="muted">Guests: <?= (int)$guests ?></div>
            <div class="muted">Check-in: <?= htmlspecialchars($check_in) ?> · Check-out: <?= htmlspecialchars($check_out) ?></div>
            <hr>
            <div class="h3" style="margin:0">$<?= number_format($amount_cents/100, 2) ?> <small class="muted">paid</small></div>
            <div class="muted" style="margin-top:8px">Booking #<?= (int)$booking_id ?></div>
            <div style="margin-top:14px">
              <a class="btn" href="my_bookings.php">View My Bookings</a>
              <a class="btn ghost" href="rooms_list.php" style="margin-left:8px">Back to Rooms</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php
  require 'footer.php';

} catch (Exception $e) {
  error_log("Stripe success error: ".$e->getMessage());
  header("Location: cancel.php"); exit;
}