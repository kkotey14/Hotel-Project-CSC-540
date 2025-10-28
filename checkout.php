<?php
// checkout.php — inventory-aware pre-confirmation page
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/header.php';

// ---- read & validate inputs ----
$room_id = (int)($_GET['room_id'] ?? 0);
$ci      = trim($_GET['ci'] ?? '');
$co      = trim($_GET['co'] ?? '');
$guests  = max(1, (int)($_GET['guests'] ?? 1));

function valid_ymd($s){
  $dt = DateTime::createFromFormat('Y-m-d', $s);
  return $dt && $dt->format('Y-m-d') === $s;
}

if ($room_id <= 0 || !valid_ymd($ci) || !valid_ymd($co) || $ci >= $co) {
  echo "<section class='container'><div class='card' style='padding:22px;max-width:700px;margin:0 auto'>
          <h2>Invalid selection</h2>
          <p>Please select valid check-in and check-out dates.</p>
          <a class='btn' href='rooms_list.php'>Back to rooms</a>
        </div></section>";
  require 'footer.php'; exit;
}

// ---- load room (this represents a room *type* with inventory) ----
$st = $pdo->prepare("SELECT * FROM rooms WHERE id=? AND is_active=1 LIMIT 1");
$st->execute([$room_id]);
$room = $st->fetch(PDO::FETCH_ASSOC);
if (!$room) {
  echo "<section class='container'><div class='card' style='padding:22px;max-width:700px;margin:0 auto'>
          <h2>Room not found</h2>
          <a class='btn' href='rooms_list.php'>Back to rooms</a>
        </div></section>";
  require 'footer.php'; exit;
}

// guest cap
if ($guests > (int)$room['max_guests']) {
  echo "<section class='container'><div class='card' style='padding:22px;max-width:700px;margin:0 auto'>
          <h2>Too many guests</h2>
          <p>This room allows up to ".(int)$room['max_guests']." guest(s).</p>
          <a class='btn' href='rooms_list.php'>Back to rooms</a>
        </div></section>";
  require 'footer.php'; exit;
}

// ---- inventory-aware availability (sold out only if all units taken) ----
$inventory = isset($room['inventory']) ? max(1, (int)$room['inventory']) : 1;

$overlap = $pdo->prepare("
  SELECT COUNT(*) FROM bookings
   WHERE room_id = ?
     AND status IN ('pending','confirmed')
     AND NOT (check_out <= ? OR check_in >= ?)
");
$overlap->execute([$room_id, $ci, $co]);
$used = (int)$overlap->fetchColumn();

if ($used >= $inventory) {
  echo "<section class='container'><div class='card' style='padding:22px;max-width:760px;margin:0 auto'>
          <h2>Invalid selection</h2>
          <p>Selected dates are no longer available for this room.</p>
          <a class='btn' href='rooms_list.php?ci=".urlencode($ci)."&co=".urlencode($co)."&guests=".(int)$guests."'>Back to rooms</a>
        </div></section>";
  require 'footer.php'; exit;
}

// ---- price estimate ----
$nights = (new DateTime($ci))->diff(new DateTime($co))->days;
$nights = max(1, $nights);
$total_cents = $nights * (int)$room['rate_cents'];
?>
<section class="container">
  <div class="card" style="max-width:900px;margin:0 auto;padding:18px 18px 24px">
    <div class="grid">
      <div class="span-4">
        <div class="hero-img-wrap" style="border-radius:12px;overflow:hidden">
          <img src="<?= htmlspecialchars($room['image_url'] ?: 'https://via.placeholder.com/800x600?text=Room') ?>"
               alt="Room image" style="width:100%;height:180px;object-fit:cover">
        </div>
        <div class="muted tiny" style="margin-top:6px">
          You won’t be charged now. You’ll pay at the property.
        </div>
      </div>

      <div class="span-8">
        <h2 class="h2" style="margin:0 0 10px">Review your stay</h2>
        <table class="table" style="width:100%;border-collapse:collapse">
          <tr>
            <td style="width:40%;border:1px solid var(--line);padding:10px">Room Type</td>
            <td style="border:1px solid var(--line);padding:10px"><?= htmlspecialchars($room['type']) ?></td>
          </tr>
          <tr>
            <td style="border:1px solid var(--line);padding:10px">Guests</td>
            <td style="border:1px solid var(--line);padding:10px"><?= (int)$guests ?> (max <?= (int)$room['max_guests'] ?>)</td>
          </tr>
          <tr>
            <td style="border:1px solid var(--line);padding:10px">Check-in</td>
            <td style="border:1px solid var(--line);padding:10px"><?= htmlspecialchars($ci) ?></td>
          </tr>
          <tr>
            <td style="border:1px solid var(--line);padding:10px">Check-out</td>
            <td style="border:1px solid var(--line);padding:10px"><?= htmlspecialchars($co) ?></td>
          </tr>
          <tr>
            <td style="border:1px solid var(--line);padding:10px">Estimate</td>
            <td style="border:1px solid var(--line);padding:10px">
              $<?= number_format($total_cents/100, 2) ?> for <?= $nights ?> night<?= $nights>1?'s':'' ?>
            </td>
          </tr>
        </table>

        <form method="post" action="bookings_new.php" style="margin-top:14px;display:flex;gap:10px">
          <input type="hidden" name="confirm" value="1">
          <input type="hidden" name="room_id" value="<?= (int)$room_id ?>">
          <input type="hidden" name="ci" value="<?= htmlspecialchars($ci) ?>">
          <input type="hidden" name="co" value="<?= htmlspecialchars($co) ?>">
          <input type="hidden" name="guests" value="<?= (int)$guests ?>">
          <button class="btn primary" type="submit">Confirm reservation</button>
          <a class="btn" href="rooms_list.php?ci=<?= urlencode($ci) ?>&co=<?= urlencode($co) ?>&guests=<?= (int)$guests ?>">Cancel</a>
        </form>

        <div class="muted tiny" style="margin-top:8px">
          The specific room number will be assigned automatically after you confirm, based on availability.
        </div>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__.'/footer.php'; ?>