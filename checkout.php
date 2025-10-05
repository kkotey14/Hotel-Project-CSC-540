<?php
// checkout.php — shows order summary and sends user to Stripe Checkout
require 'db.php';
require 'auth.php';
require_login();
require 'header.php';

// Inputs
$room_id = (int)($_GET['room_id'] ?? 0);
$ci      = $_GET['ci'] ?? '';
$co      = $_GET['co'] ?? '';
$guests  = max(1, (int)($_GET['guests'] ?? 1));

// Validate room
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id=? AND is_active=1");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) {
  echo "<section class='container'><div class='card'>Room not found or inactive.</div></section>";
  require 'footer.php'; exit;
}

// Validate dates
if (!$ci || !$co || $ci >= $co) {
  echo "<section class='container'><div class='card'>Invalid dates. Please go back and choose a valid range.</div></section>";
  require 'footer.php'; exit;
}

// Validate guests vs max
if ($guests > (int)$room['max_guests']) {
  echo "<section class='container'><div class='card'>Guest count exceeds room limit (max {$room['max_guests']}).</div></section>";
  require 'footer.php'; exit;
}

// Compute nights & total
$nightStmt = $pdo->prepare("SELECT DATEDIFF(?, ?) AS nights");
$nightStmt->execute([$co, $ci]);
$diff = (int)$nightStmt->fetchColumn();
$nights = max(1, $diff);
$amount_cents = $nights * (int)$room['rate_cents'];
?>
<section class="container">
  <div class="card" style="padding:24px;max-width:760px;margin:0 auto;">
    <h1 class="h2" style="margin:0 0 10px">Checkout</h1>
    <p class="muted" style="margin-top:0">Your booking will be created only after payment is completed.</p>

    <div class="grid">
      <div class="span-7">
        <div class="hero-img-wrap">
          <img src="<?= htmlspecialchars($room['image_url'] ?: 'https://via.placeholder.com/1200x800?text=Room') ?>" alt="Room">
        </div>
      </div>
      <div class="span-5">
        <div class="card" style="padding:16px">
          <div class="h3" style="margin:0 0 6px"><?= htmlspecialchars($room['type']) ?> · Room <?= htmlspecialchars($room['number']) ?></div>
          <div class="muted">Guests: <?= (int)$guests ?> · Max <?= (int)$room['max_guests'] ?></div>
          <div class="muted">Check-in: <?= htmlspecialchars($ci) ?> · Check-out: <?= htmlspecialchars($co) ?> (<?= $nights ?> night<?= $nights>1?'s':'' ?>)</div>
          <hr>
          <div class="h3" style="margin:0">$<?= number_format($amount_cents/100, 2) ?> <small class="muted">total</small></div>
        </div>

        <form method="post" action="create_checkout_session.php" style="margin-top:12px">
          <input type="hidden" name="room_id" value="<?= $room_id ?>">
          <input type="hidden" name="ci" value="<?= htmlspecialchars($ci) ?>">
          <input type="hidden" name="co" value="<?= htmlspecialchars($co) ?>">
          <input type="hidden" name="guests" value="<?= (int)$guests ?>">
          <button class="btn primary" type="submit">Pay & Confirm</button>
          <a class="btn" href="rooms_list.php" style="margin-left:8px">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</section>
<?php require 'footer.php'; ?>