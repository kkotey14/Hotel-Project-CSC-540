<?php
require 'db.php'; require 'auth.php'; require 'header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id=? AND is_active=1");
$stmt->execute([$id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) { echo "<section class='card'><h2>Room not found</h2></section>"; require 'footer.php'; exit; }

/* Photos */
$pq = $pdo->prepare("SELECT url, photo_type, caption FROM room_photos WHERE room_id=? ORDER BY id DESC");
$pq->execute([$id]);
$photos = $pq->fetchAll(PDO::FETCH_ASSOC);

$main = array_values(array_filter($photos, fn($x)=>$x['photo_type']==='main'));
$bath = array_values(array_filter($photos, fn($x)=>$x['photo_type']==='bathroom'));
$other = array_values(array_filter($photos, fn($x)=>$x['photo_type']==='other'));

$gallery = $main ?: ($room['image_url'] ? [['url'=>$room['image_url'],'photo_type'=>'main','caption'=>null]] : []);

/* Reviews + avg */
$reviewsStmt = $pdo->prepare("SELECT r.*, u.name FROM reviews r JOIN users u ON r.user_id=u.id WHERE r.room_id=? ORDER BY r.created_at DESC");
$reviewsStmt->execute([$id]);
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);
$avgRow = $pdo->prepare("SELECT ROUND(AVG(rating),1) as avg_rating, COUNT(*) as cnt FROM reviews WHERE room_id=?");
$avgRow->execute([$id]);
$avg = $avgRow->fetch(PDO::FETCH_ASSOC);
$avgText = ($avg && $avg['cnt']) ? "{$avg['avg_rating']} ★ ({$avg['cnt']} review".($avg['cnt']>1?'s':'').")" : "No reviews yet";

/* Disabled dates */
$blk = $pdo->prepare("SELECT check_in, check_out FROM bookings WHERE room_id=? AND status IN ('pending','confirmed')");
$blk->execute([$id]);
$disabledRanges = [];
foreach ($blk->fetchAll(PDO::FETCH_ASSOC) as $b) {
  $disabledRanges[] = ['from' => $b['check_in'], 'to' => $b['check_out']];
}
?>

<section class="container">
  <!-- Hero image -->
  <div class="hero-img-wrap hero-banner" style="margin-top:16px">
    <img id="mainPhoto" src="<?= htmlspecialchars($gallery[0]['url'] ?? 'https://via.placeholder.com/1600x900?text=Room') ?>" alt="Room photo" style="width:100%;height:460px;object-fit:cover">
    <div class="hero-overlay"></div>
    <div class="hero-bar container">
      <div>
        <h1 class="h2" style="color:#fff;margin:0"><?= htmlspecialchars($room['type']) ?> — Room <?= htmlspecialchars($room['number']) ?></h1>
        <div class="muted" style="color:#e7edf6">Max <?= (int)$room['max_guests'] ?> · <?= $avgText ?></div>
      </div>
<?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','staff'])): ?>
  <div style="margin-top:15px;">
    <a class="btn" href="edit_guest_limit.php?id=<?= $room['id'] ?>">Edit Guest Limit</a>
  </div>
<?php endif; ?>
      <div style="text-align:right">
        <div class="price" style="color:#fff">$<?= number_format($room['rate_cents']/100, 2) ?> <small>/ night</small></div>
        <a class="btn primary" href="#book" style="margin-top:8px">Book now</a>
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

    <!-- Booking -->
    <form class="booking-grid" action="bookings_new.php?room_id=<?= $room['id'] ?>" method="post">
      <label>Dates
        <input class="input range" id="date_range" placeholder="Select your stay…" readonly>
        <input type="hidden" name="check_in" id="check_in">
        <input type="hidden" name="check_out" id="check_out">
      </label>
      <button class="btn primary" type="submit">Continue to checkout</button>
    </form>

    <!-- Thumbs (main photos) -->
    <?php if ($gallery): ?>
      <div class="thumbs">
        <?php foreach($gallery as $g): ?>
          <button class="thumb" data-src="<?= htmlspecialchars($g['url']) ?>" title="<?= htmlspecialchars($g['caption'] ?? '') ?>">
            <img src="<?= htmlspecialchars($g['url']) ?>" alt="Photo">
          </button>
        <?php endforeach; ?>
        <?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin','staff'])): ?>
          <a class="btn" href="admin_room_photos.php?room_id=<?= $room['id'] ?>" style="margin-left:8px">Manage photos</a>
        <?php endif; ?>
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
      <form method="post" class="reviewForm" action="room_review_add.php?id=<?= $room['id'] ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
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
  // Gallery thumb → main
  document.querySelectorAll('.thumb').forEach(btn => {
    btn.addEventListener('click', () => {
      const src = btn.getAttribute('data-src');
      const main = document.getElementById('mainPhoto');
      if (src && main) main.src = src;
    });
  });

  // Calendar
  const disabledRanges = <?= json_encode($disabledRanges, JSON_UNESCAPED_SLASHES) ?>;
  const rangeInput = document.getElementById('date_range');
  const checkIn    = document.getElementById('check_in');
  const checkOut   = document.getElementById('check_out');
  const today = new Date(); today.setHours(0,0,0,0);
  function ymd(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}

  const fp = flatpickr(rangeInput, {
    mode:"range", dateFormat:"Y-m-d", minDate: today, disable: disabledRanges, showMonths: 2, allowInput:false,
    onChange(sel){ if(sel.length===2){ checkIn.value=ymd(sel[0]); checkOut.value=ymd(sel[1]); } else { checkIn.value=''; checkOut.value=''; } }
  });

  document.querySelector('.booking-grid').addEventListener('submit', e=>{
    if(!checkIn.value || !checkOut.value){ e.preventDefault(); alert('Please select your dates.'); }
  });
</script>

<?php require 'footer.php'; ?>
