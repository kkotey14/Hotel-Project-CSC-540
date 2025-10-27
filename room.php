<?php
require 'db.php'; require 'auth.php'; require 'header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id=? AND is_active=1 LIMIT 1");
$stmt->execute([$id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) {
  echo "<section class='container'><div class='card'><h2>Room not found</h2><a class='btn' href='rooms_list.php'>Back to rooms</a></div></section>";
  require 'footer.php'; exit;
}

/* Photos */
$pq = $pdo->prepare("SELECT url, photo_type, caption FROM room_photos WHERE room_id=? ORDER BY id DESC");
$pq->execute([$id]);
$photos = $pq->fetchAll(PDO::FETCH_ASSOC);

$main  = array_values(array_filter($photos, fn($x)=>$x['photo_type']==='main'));
$bath  = array_values(array_filter($photos, fn($x)=>$x['photo_type']==='bathroom'));
$gallery = $main ?: ($room['image_url'] ? [['url'=>$room['image_url'],'photo_type'=>'main','caption'=>null]] : []);

/* Reviews + avg */
$reviewsStmt = $pdo->prepare("SELECT r.*, u.name FROM reviews r JOIN users u ON r.user_id=u.id WHERE r.room_id=? ORDER BY r.created_at DESC");
$reviewsStmt->execute([$id]);
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);
$avgRow = $pdo->prepare("SELECT ROUND(AVG(rating),1) as avg_rating, COUNT(*) as cnt FROM reviews WHERE room_id=?");
$avgRow->execute([$id]);
$avg = $avgRow->fetch(PDO::FETCH_ASSOC);
$avgText = ($avg && $avg['cnt']) ? "{$avg['avg_rating']} ★ ({$avg['cnt']} review".($avg['cnt']>1?'s':'').")" : "No reviews yet";

/* Prefill from query */
$prefill_ci     = $_GET['ci'] ?? '';
$prefill_co     = $_GET['co'] ?? '';
$prefill_guests = isset($_GET['guests']) && $_GET['guests'] !== '' ? max(1,(int)$_GET['guests']) : 1;

/* -------- Availability model: disable only SOLD-OUT dates -------- */
$inventory = isset($room['inventory']) ? max(1,(int)$room['inventory']) : 1;

// Horizon (how far to compute/disable). Adjust as you like.
$hStart = date('Y-m-d');
$hEnd   = date('Y-m-d', strtotime('+18 months'));

// Fetch all bookings overlapping the horizon
$bookStmt = $pdo->prepare("
  SELECT check_in, check_out
  FROM bookings
  WHERE room_id = ?
    AND status IN ('pending','confirmed')
    AND check_out >= ?
    AND check_in  <= ?
");
$bookStmt->execute([$id, $hStart, $hEnd]);
$rows = $bookStmt->fetchAll(PDO::FETCH_ASSOC);

// Count per-night occupancy (checkout day is NOT a night)
$perDay = [];
foreach ($rows as $b) {
  $start = max($b['check_in'], $hStart);
  $end   = min($b['check_out'], $hEnd);
  $t = strtotime($start);
  $tEnd = strtotime($end);
  while ($t < $tEnd) { // up to day before checkout
    $k = date('Y-m-d', $t);
    $perDay[$k] = ($perDay[$k] ?? 0) + 1;
    $t = strtotime('+1 day', $t);
  }
}

// Compress into disabled ranges only where perDay >= inventory
$disabledRanges = [];
$cur = strtotime($hStart);
$lim = strtotime($hEnd);
$inBlock = false;
$blockStart = null;

while ($cur < $lim) {
  $d = date('Y-m-d', $cur);
  $soldOut = (($perDay[$d] ?? 0) >= $inventory);

  if ($soldOut && !$inBlock) {
    $inBlock = true;
    $blockStart = $d;
  } elseif (!$soldOut && $inBlock) {
    $disabledRanges[] = ['from'=>$blockStart, 'to'=>$d];
    $inBlock = false; $blockStart = null;
  }
  $cur = strtotime('+1 day', $cur);
}
if ($inBlock && $blockStart) {
  $disabledRanges[] = ['from'=>$blockStart, 'to'=>$hEnd];
}

/* Admin/staff debug toggle */
$showDebug = (!empty($_GET['debug']) && $_GET['debug']=='1') ||
             (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','staff']));
?>
<section class="container">
  <div class="hero-img-wrap hero-banner" style="margin-top:16px">
    <img id="mainPhoto" src="<?= htmlspecialchars($gallery[0]['url'] ?? 'https://via.placeholder.com/1600x900?text=Room') ?>" alt="Room photo" style="width:100%;height:460px;object-fit:cover">
    <div class="hero-overlay"></div>
    <div class="hero-bar container">
      <div>
        <h1 class="h2" style="color:#fff;margin:0"><?= htmlspecialchars($room['type']) ?></h1>
        <div class="muted" style="color:#e7edf6">Max <?= (int)$room['max_guests'] ?> · <?= $avgText ?></div>
        <?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','staff'])): ?>
          <div style="margin-top:10px">
            <a class="btn" href="edit_guest_limit.php?id=<?= (int)$room['id'] ?>">Edit Guest Limit</a>
          </div>
        <?php endif; ?>
      </div>
      <div style="text-align:right">
        <div class="h3" style="color:#fff;margin:0">$<?= number_format($room['rate_cents']/100, 2) ?> <small class="muted">/ night</small></div>
        <button class="btn primary" type="button" id="heroReserveBtn" style="margin-top:8px">Reserve</button>
      </div>
    </div>
  </div>
</section>

<section class="container" id="book">
  <div class="card">
    <?php if (!empty($room['description'])): ?>
      <p class="lead" style="margin:6px 0 14px"><?= nl2br(htmlspecialchars($room['description'])) ?></p>
    <?php endif; ?>

    <div class="amenities" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
      <span class="chip">📶 Fast Wi-Fi</span><span class="chip">📺 Smart TV</span>
      <span class="chip">🧴 Toiletries</span><span class="chip">☕ Coffee Maker</span>
      <span class="chip">🧊 Mini-fridge</span>
    </div>

    <form id="reserveForm" class="booking-grid" action="checkout.php" method="get">
      <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
      <input type="hidden" name="ci" id="ci" value="<?= htmlspecialchars($prefill_ci) ?>">
      <input type="hidden" name="co" id="co" value="<?= htmlspecialchars($prefill_co) ?>">

      <label style="margin-bottom:0">Dates
        <input class="input range" id="date_range" placeholder="Select your stay…" readonly>
      </label>

      <div style="display:flex;gap:10px;align-items:end">
        <label class="tiny muted" style="flex:1">
          Guests
          <input class="input" type="number" id="guests" name="guests" min="1" max="<?= (int)$room['max_guests'] ?>" value="<?= (int)$prefill_guests ?>">
        </label>
        <button class="btn primary" type="submit">Continue to checkout</button>
      </div>

      <div id="reserveHint" class="tiny muted" style="margin-top:8px;display:none"></div>
    </form>

    <?php if ($gallery): ?>
      <div class="thumbs" style="margin-top:12px">
        <?php foreach($gallery as $g): ?>
          <button class="thumb" data-src="<?= htmlspecialchars($g['url']) ?>" title="<?= htmlspecialchars($g['caption'] ?? '') ?>">
            <img src="<?= htmlspecialchars($g['url']) ?>" alt="Photo">
          </button>
        <?php endforeach; ?>
        <?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','staff'])): ?>
          <a class="btn" href="admin_room_photos.php?room_id=<?= (int)$room['id'] ?>" style="margin-left:8px">Manage photos</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($showDebug): ?>
      <div class="card" style="margin-top:14px;background:#f9fafb">
        <div class="muted tiny"><strong>Debug availability</strong> — inventory: <?= (int)$inventory ?> (sold-out dates shown in calendar)</div>
        <pre class="tiny" style="white-space:pre-wrap;max-height:160px;overflow:auto;border:1px solid #eee;padding:8px;background:#fff">
<?= htmlspecialchars(json_encode(['perDay'=>$perDay,'disabledRanges'=>$disabledRanges], JSON_PRETTY_PRINT)) ?>
        </pre>
        <div class="tiny muted">Tip: add <code>?debug=1</code> to the URL to show/hide this.</div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($bath): ?>
<section class="container">
  <div class="card">
    <h3 class="h3" style="margin:0 0 12px">Bathroom</h3>
    <div class="grid">
      <?php foreach($bath as $p): ?>
        <div class="span-4">
          <div class="hero-img-wrap">
            <img src="<?= htmlspecialchars($p['url']) ?>" alt="Bathroom" style="width:100%;height:220px;object-fit:cover">
          </div>
          <?php if(!empty($p['caption'])): ?><div class="tiny muted" style="margin-top:6px"><?= htmlspecialchars($p['caption']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="container">
  <div class="card">
    <h3 class="h3" style="margin:0 0 12px">Reviews</h3>
    <?php if (!empty($_SESSION['user'])): ?>
      <form method="post" class="reviewForm" action="room_review_add.php?id=<?= (int)$room['id'] ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
        <label>Rating
          <select name="rating" class="input" required>
            <option value="">Select…</option>
            <?php for($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= $i ?> ★</option><?php endfor; ?>
          </select>
        </label>
        <label style="flex:1">Comment (optional)
          <textarea name="comment" class="input" rows="3" placeholder="What did you like?"></textarea>
        </label>
        <button class="btn">Submit review</button>
      </form>
    <?php else: ?>
      <p><a class="btn" href="login.php">Log in</a> to write a review.</p>
    <?php endif; ?>

    <?php if (!$reviews): ?>
      <p class="muted">No reviews yet.</p>
    <?php else: ?>
      <?php foreach($reviews as $rev): ?>
        <div class="reviewRow" style="border-top:1px solid var(--line);padding:12px 0">
          <div style="display:flex;gap:10px;align-items:center">
            <strong><?= htmlspecialchars($rev['name']) ?></strong>
            <span class="stars" style="color:#f5a524;font-weight:800"><?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5-(int)$rev['rating']) ?></span>
            <span class="muted"><?= htmlspecialchars($rev['created_at']) ?></span>
          </div>
          <?php if(!empty($rev['comment'])): ?><p><?= nl2br(htmlspecialchars($rev['comment'])) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  // Thumbs
  document.querySelectorAll('.thumb').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const src = btn.getAttribute('data-src');
      const main = document.getElementById('mainPhoto');
      if (src && main) main.src = src;
    });
  });

  const disabledRanges = <?= json_encode($disabledRanges, JSON_UNESCAPED_SLASHES) ?>; // <- only sold-out
  const rangeInput = document.getElementById('date_range');
  const hiddenCI   = document.getElementById('ci');
  const hiddenCO   = document.getElementById('co');
  const guestsEl   = document.getElementById('guests');
  const hint       = document.getElementById('reserveHint');
  function ymd(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
  const today = new Date(); today.setHours(0,0,0,0);

  const preCI = hiddenCI.value ? new Date(hiddenCI.value) : null;
  const preCO = hiddenCO.value ? new Date(hiddenCO.value) : null;

  const fp = flatpickr(rangeInput, {
    mode:"range",
    dateFormat:"Y-m-d",
    minDate: today,
    showMonths: 2,
    allowInput:false,
    disable: disabledRanges,        // << wired here
    defaultDate: (preCI && preCO) ? [preCI, preCO] : null,
    onChange(sel){
      if(sel.length===2){ hiddenCI.value=ymd(sel[0]); hiddenCO.value=ymd(sel[1]); if(hint) hint.style.display='none'; }
      else { hiddenCI.value=''; hiddenCO.value=''; }
    }
  });

  document.getElementById('heroReserveBtn').addEventListener('click', ()=>{
    const form = document.getElementById('reserveForm');
    if(!hiddenCI.value || !hiddenCO.value){
      const sel = fp.selectedDates||[];
      if(sel.length===2){ hiddenCI.value=ymd(sel[0]); hiddenCO.value=ymd(sel[1]); }
    }
    if(!hiddenCI.value || !hiddenCO.value){
      if(hint){ hint.textContent='Please select check-in and check-out dates before reserving.'; hint.style.display='block'; }
      rangeInput.focus(); return;
    }
    const g = parseInt(guestsEl.value||'1',10);
    guestsEl.value = Math.max(1, Math.min(g, <?= (int)$room['max_guests'] ?>));
    form.submit();
  });

  document.getElementById('reserveForm').addEventListener('submit', (e)=>{
    if(!hiddenCI.value || !hiddenCO.value){
      const sel = fp.selectedDates||[];
      if(sel.length===2){ hiddenCI.value=ymd(sel[0]); hiddenCO.value=ymd(sel[1]); }
    }
    if(!hiddenCI.value || !hiddenCO.value){
      e.preventDefault(); if(hint){ hint.textContent='Please select check-in and check-out dates.'; hint.style.display='block'; }
      rangeInput.focus();
    } else {
      const g = parseInt(guestsEl.value||'1',10);
      guestsEl.value = Math.max(1, Math.min(g, <?= (int)$room['max_guests'] ?>));
    }
  });
</script>
<?php require 'footer.php'; ?>