<?php

// DEBUG (remove after fixing)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// create_checkout_session.php — verifies availability and creates Stripe Checkout Session
require_once __DIR__ . '/config.php';   // <- ensure STRIPE_* and BASE_URL are defined
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

// Stripe SDK
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
  die('<pre>Stripe SDK not found. Run: composer install</pre>');
}
require_once $autoload;

if (!defined('STRIPE_SECRET') || !STRIPE_SECRET) {
  die('<pre>STRIPE_SECRET is missing. Put test keys in config.php (local only).</pre>');
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET);

// Inputs
$room_id = (int)($_POST['room_id'] ?? 0);
$ci      = $_POST['ci'] ?? '';
$co      = $_POST['co'] ?? '';
$guests  = max(1, (int)($_POST['guests'] ?? 1));

// Validate room
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id=? AND is_active=1");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) { header("Location: rooms_list.php"); exit; }

// Validate dates
if (!$ci || !$co || $ci >= $co) { header("Location: room.php?id=".$room_id); exit; }

// Validate guests vs max
if ($guests > (int)$room['max_guests']) { header("Location: room.php?id=".$room_id."&err=max_guests"); exit; }

// Check availability again
$q = $pdo->prepare("SELECT COUNT(*) FROM bookings
  WHERE room_id=? AND status IN ('pending','confirmed')
    AND ( (check_in < ? AND check_out > ?) OR (check_in >= ? AND check_in < ?) )");
$q->execute([$room_id, $co, $ci, $ci, $co]);
$conflict = $q->fetchColumn() > 0;
if ($conflict) { header("Location: room.php?id=".$room_id."&err=conflict"); exit; }

// Compute nights & amount
$nightStmt = $pdo->prepare("SELECT DATEDIFF(?, ?) AS nights");
$nightStmt->execute([$co, $ci]);
$diff = (int)$nightStmt->fetchColumn();
$nights = max(1, $diff);
$amount_cents = $nights * (int)$room['rate_cents'];

// Safe BASE_URL fallback if not defined
$base = (defined('BASE_URL') && BASE_URL) ? BASE_URL : '/hotelapp';

try {
  $session = \Stripe\Checkout\Session::create([
    'mode' => 'payment',
    'payment_method_types' => ['card'],
    'line_items' => [[
      'quantity' => 1,
      'price_data' => [
        'currency' => 'usd',
        'unit_amount' => $amount_cents,
        'product_data' => [
          'name' => "Room {$room['number']} – {$room['type']} ({$nights} night".($nights>1?'s':'').")",
          'description' => "Check-in: {$ci} | Check-out: {$co} | Guests: {$guests}",
        ],
      ],
    ]],
    'success_url' => 'http://localhost/hotelapp/success.php?session_id={CHECKOUT_SESSION_ID}',
'cancel_url'  => 'http://localhost/hotelapp/cancel.php',
    'metadata' => [
      'user_id'    => $_SESSION['user']['id'],
      'room_id'    => $room_id,
      'check_in'   => $ci,
      'check_out'  => $co,
      'guests'     => $guests,
      'nights'     => $nights,
      'rate_cents' => (int)$room['rate_cents'],
    ]
  ]);

  header("Location: " . $session->url);
  exit;

} catch (Exception $e) {
  // Show the error message while debugging; log it in prod
  echo "<pre>Stripe error: " . htmlspecialchars($e->getMessage()) . "</pre>";
  error_log("Stripe create session error: ".$e->getMessage());
  exit;
}