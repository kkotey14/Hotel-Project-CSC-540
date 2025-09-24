<?php
require 'db.php'; require 'auth.php'; require_login(); require 'header.php';

$booking_id = (int)($_GET['booking_id'] ?? 0);
$stmt = $pdo->prepare("SELECT b.*, r.number, r.type, r.rate_cents,
  (SELECT amount_cents FROM payments WHERE booking_id=b.id ORDER BY id DESC LIMIT 1) AS amount_cents
  FROM bookings b JOIN rooms r ON b.room_id=r.id
  WHERE b.id=? AND b.user_id=?");
$stmt->execute([$booking_id, $_SESSION['user']['id']]);
$bk = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bk) { echo "<section class='card'><h2>Booking not found</h2></section>"; require 'footer.php'; exit; }

$amount = number_format($bk['amount_cents']/100, 2);
?>
<section class="card">
  <h2>Checkout</h2>
  <p><b>Room:</b> <?= htmlspecialchars($bk['type']) ?> (<?= htmlspecialchars($bk['number']) ?>)</p>
  <p><b>Dates:</b> <?= htmlspecialchars($bk['check_in']) ?> → <?= htmlspecialchars($bk['check_out']) ?></p>
  <p><b>Total:</b> $<?= $amount ?></p>
  <button id="payBtn" class="btn">Pay with card</button>
</section>

<script src="https://js.stripe.com/v3/"></script>
<script>
  const stripe = Stripe("<?= STRIPE_PUBLISHABLE ?>");
  document.getElementById('payBtn').addEventListener('click', async () => {
    const res = await fetch("create_checkout_session.php?booking_id=<?= $booking_id ?>");
    const data = await res.json();
    if (data.id) {
      stripe.redirectToCheckout({ sessionId: data.id });
    } else {
      alert('Failed to start checkout');
      console.error(data);
    }
  });
</script>
<?php require 'footer.php'; ?>
