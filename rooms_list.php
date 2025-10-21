<?php require 'db.php'; require 'auth.php'; require 'header.php'; ?>

<?php
// Admin create
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','staff'])) {
  $number = trim($_POST['number'] ?? '');
  $type   = trim($_POST['type'] ?? '');
  $rate   = (float)($_POST['rate'] ?? 0);
  $max    = (int)($_POST['max_guests'] ?? 2);
  $img    = trim($_POST['image_url'] ?? '');
  $desc   = trim($_POST['description'] ?? '');
  if ($number && $type && $rate > 0) {
    $stmt = $pdo->prepare("INSERT INTO rooms (number,type,image_url,description,rate_cents,max_guests) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$number,$type,$img,$desc,(int)round($rate*100),$max]);
  }
  header("Location: rooms_list.php"); exit;
}

// Inputs
$ci = $_GET['ci'] ?? '';
$co = $_GET['co'] ?? '';
$guests = isset($_GET['guests']) && (int)$_GET['guests'] > 0 ? (int)$_GET['guests'] : null;

// If guests > 5 → no rooms by policy
$forceEmpty = ($guests && $guests > 5);

// Build room query (filter by guests if provided and not forced empty)
if (!$forceEmpty) {
  if ($guests) {
    $q = $pdo->prepare("
      SELECT r.*,
            (SELECT ROUND(AVG(rating),1) FROM reviews WHERE room_id=r.id) AS avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE room_id=r.id) AS review_count
      FROM rooms r
      WHERE r.is_active=1
        AND r.max_guests >= ?
      ORDER BY r.id DESC
    ");
    $q->execute([$guests]);
  } else {
    $q = $pdo->query("
      SELECT r.*,
            (SELECT ROUND(AVG(rating),1) FROM reviews WHERE room_id=r.id) AS avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE room_id=r.id) AS review_count
      FROM rooms r
      WHERE r.is_active=1
      ORDER BY r.id DESC
    ");
  }
  $rooms = $q->fetchAll(PDO::FETCH_ASSOC);
} else {
  $rooms = [];
}
?>
<section class="container">
  <div class="card" style="padding:24px">
    <h1 class="h2" style="margin:0 0 8px">Find your stay</h1>
    <p class="muted" style="margin:0 0 12px">Pick dates, set guests, browse rooms.</p>
    <form class="search-bar" method="get" action="rooms_list.php" id="searchForm">
      <div class="search-grid">
        <input class="input range" id="srch_range" placeholder="Select dates" readonly>
        <input type="hidden" name="ci" id="ci" value="<?= htmlspecialchars($ci) ?>">
        <input type="hidden" name="co" id="co" value="<?= htmlspecialchars($co) ?>">
        <input class="input" type="number" name="guests" min="1" placeholder="Guests" value="<?= htmlspecialchars($guests ?? '') ?>">
        <button class="btn primary">Search</button>
      </div>
    </form>
    <?php if ($guests): ?>
      <div class="tiny muted" style="margin-top:8px">Filtering rooms that allow <strong><?= (int)$guests ?></strong> guest<?= $guests>1?'s':''; ?>.</div>
    <?php endif; ?>
  </div>
</section>

<section class="container">
  <?php if ($forceEmpty || count($rooms) === 0): ?>
    <div class="card" style="padding:28px; text-align:center">
      <h2 class="h2" style="margin:0 0 6px">No rooms available</h2>
      <?php if ($forceEmpty): ?>
        <p class="muted">We’re unable to accommodate more than 5 guests in a single room. Try reducing the guest count or booking multiple rooms.</p>
      <?php else: ?>
        <p class="muted">Try different dates or guest count, or check again later.</p>
      <?php endif; ?>
      <div style="margin-top:12px">
        <a class="btn" href="rooms_list.php">Clear filters</a>
      </div>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($rooms as $r): ?>
        <article class="span-4 room-card">
          <a class="room-media" href="room.php?id=<?= $r['id'] ?>&<?= $guests ? 'guests='.$guests.'&' : '' ?>ci=<?= urlencode($ci) ?>&co=<?= urlencode($co) ?>">
            <img src="<?= htmlspecialchars($r['image_url'] ?: 'https://via.placeholder.com/1200x800?text=Room') ?>" alt="Room <?= htmlspecialchars($r['number']) ?>">
            <?php if ($r['avg_rating'] !== null): ?><span class="badge"><?= htmlspecialchars($r['avg_rating']) ?> ★</span><?php endif; ?>
          </a>
          <div class="room-body">
            <div class="room-top">
              <div>
                <div class="h3" style="margin:0"><?= htmlspecialchars($r['type']) ?></div>
                <div class="muted">Room <?= htmlspecialchars($r['number']) ?> · Max <?= (int)$r['max_guests'] ?></div>
              </div>
              <div class="price">$<?= number_format($r['rate_cents']/100, 2) ?> <small>/ night</small></div>
            </div>
            <?php if (!empty($r['description'])): ?><p class="muted" style="margin:10px 0 0"><?= htmlspecialchars($r['description']) ?></p><?php endif; ?>
            <div class="chips" style="margin-top:12px">
              <span class="chip">Wi-Fi</span><span class="chip">Ensuite</span><span class="chip">Comfort</span>
            </div>
            <div class="actions">
              <?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','staff'])): ?>
                <a class="btn" href="room_edit.php?id=<?= $r['id'] ?>">Edit</a>
              <?php endif; ?>
              <a class="btn primary"
   href="checkout.php?room_id=<?= (int)$r['id'] ?>&ci=<?= urlencode($ci ?? '') ?>&co=<?= urlencode($co ?? '') ?>&guests=<?= (int)max(1, $guests ?? 1) ?>">
  Proceed to Checkout
</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','staff'])): ?>
<section class="container">
  <div class="card">
    <h2 class="h2">Add a Room</h2>
    <form method="post" class="grid">
      <div class="span-4"><label>Number<input class="input" name="number" required></label></div>
      <div class="span-4"><label>Type<input class="input" name="type" placeholder="Queen, King, Suite" required></label></div>
      <div class="span-4"><label>Rate (USD/night)<input class="input" name="rate" type="number" step="0.01" required></label></div>
      <div class="span-4"><label>Max guests<input class="input" name="max_guests" type="number" value="2" required></label></div>
      <div class="span-8"><label>Image URL<input class="input" name="image_url" placeholder="https://..."></label></div>
      <div class="span-12"><label>Description<textarea class="input" name="description" rows="3"></textarea></label></div>
      <div class="span-12"><button class="btn primary">Create</button></div>
    </form>
  </div>
</section>
<?php endif; ?>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  function ymd(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
  const rangeInput = document.getElementById('srch_range');
  const hiddenCI   = document.getElementById('ci');
  const hiddenCO   = document.getElementById('co');
  const preCI = hiddenCI.value ? new Date(hiddenCI.value) : null;
  const preCO = hiddenCO.value ? new Date(hiddenCO.value) : null;
  const today = new Date(); today.setHours(0,0,0,0);
  const fp = flatpickr(rangeInput, {
    mode:"range", dateFormat:"Y-m-d", minDate: today, showMonths: 2, allowInput:false,
    defaultDate:(preCI && preCO) ? [preCI,preCO] : null,
    onChange(sel){ if(sel.length===2){ hiddenCI.value=ymd(sel[0]); hiddenCO.value=ymd(sel[1]); } else { hiddenCI.value=''; hiddenCO.value=''; } }
  });
  document.getElementById('searchForm').addEventListener('submit', (e)=>{
    const gs = document.querySelector('input[name="guests"]').value;
    if (gs && parseInt(gs,10) < 1) { e.preventDefault(); alert('Guests must be at least 1.'); }
  });
</script>

<?php require 'footer.php'; ?>
