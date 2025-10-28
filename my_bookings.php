<?php
// my_bookings.php — latest-first list, extend (no past dates), cancel support
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_login();

$me = $_SESSION['user'];
$uid = (int)$me['id'];
$is_admin = in_array($me['role'] ?? '', ['admin','staff'], true);

$flash = $error = '';

// ---------- POST: Cancel ----------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_booking_id'])) {
  $bid = (int)($_POST['cancel_booking_id'] ?? 0);

  // User can cancel only own booking unless admin/staff
  $q = $pdo->prepare("SELECT b.*, r.inventory
                        FROM bookings b
                        JOIN rooms r ON r.id=b.room_id
                       WHERE b.id=? ".($is_admin ? "" : "AND b.user_id=?"));
  $q->execute($is_admin ? [$bid] : [$bid, $uid]);
  $bk = $q->fetch(PDO::FETCH_ASSOC);

  if (!$bk) {
    $error = 'Booking not found or not permitted.';
  } elseif ($bk['status'] === 'cancelled') {
    $flash = 'That booking is already cancelled.';
  } else {
    $up = $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
    $up->execute([$bid]);
    $flash = 'Your booking has been cancelled.';
  }
}

// ---------- POST: Extend (update check-out) ----------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['extend_booking_id'])) {
  $bid   = (int)($_POST['extend_booking_id'] ?? 0);
  $newCo = trim($_POST['new_check_out'] ?? '');

  $dt = DateTime::createFromFormat('Y-m-d', $newCo);
  if (!$dt || $dt->format('Y-m-d') !== $newCo) {
    $error = 'Please pick a valid new check-out date.';
  } else {
    // Load booking + room (restrict to owner unless admin)
    $q = $pdo->prepare("SELECT b.*, r.inventory, r.rate_cents
                          FROM bookings b
                          JOIN rooms r ON r.id=b.room_id
                         WHERE b.id=? ".($is_admin ? "" : "AND b.user_id=?"));
    $q->execute($is_admin ? [$bid] : [$bid, $uid]);
    $bk = $q->fetch(PDO::FETCH_ASSOC);

    if (!$bk) {
      $error = 'Booking not found or not permitted.';
    } elseif ($bk['status'] !== 'confirmed') {
      $error = 'Only confirmed bookings can be extended.';
    } else {
      $oldCo = $bk['check_out'];
      // Require newCo > max(today, oldCo)
      $todayYmd = (new DateTime('today'))->format('Y-m-d');
      $minAllowed = max($todayYmd, substr($oldCo, 0, 10)); // check_out stored as Y-m-d or Y-m-d H:i:s
      if ($newCo <= $minAllowed) {
        $error = 'New check-out must be after your current check-out and cannot be in the past.';
      } else {
        // Inventory check on [check_in, newCo)
        $room_id   = (int)$bk['room_id'];
        $inventory = max(1, (int)$bk['inventory']);
        $ci = substr($bk['check_in'], 0, 10);

        // Find units already taken by *other* bookings overlapping [ci, newCo)
        $st = $pdo->prepare("
          SELECT unit_number
            FROM bookings
           WHERE room_id=?
             AND status IN ('pending','confirmed')
             AND id <> ?
             AND NOT (check_out <= ? OR check_in >= ?)
             AND unit_number IS NOT NULL
           ORDER BY unit_number
        ");
        $st->execute([$room_id, $bid, $ci, $newCo]);
        $taken = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));

        // Pick a unit (prefer the current unit if possible)
        $assigned = (int)$bk['unit_number'] ?: null;
        if ($assigned !== null && in_array($assigned, $taken, true)) {
          $assigned = null; // current unit is no longer free; reassign
        }
        if ($assigned === null) {
          for ($u=1; $u<=$inventory; $u++) {
            if (!in_array($u, $taken, true)) { $assigned = $u; break; }
          }
        }
        if ($assigned === null) {
          $error = 'Sorry, that room type is fully booked for those dates.';
        } else {
          $up = $pdo->prepare("UPDATE bookings SET check_out=?, unit_number=? WHERE id=?");
          $up->execute([$newCo, $assigned, $bid]);
          $flash = 'Your stay has been extended to '.$newCo.'.';
        }
      }
    }
  }
}

// ---------- Fetch my bookings (latest first) ----------
// Prefer ordering by check_in DESC then created_at DESC as fallback.
$bookings = [];
$sql = "
  SELECT b.*, r.type AS room_type, r.rate_cents
    FROM bookings b
    JOIN rooms r ON r.id=b.room_id
";
if ($is_admin && isset($_GET['all']) && $_GET['all']==='1') {
  // Admin can view all bookings if they add ?all=1
  $sql .= " WHERE 1=1 ";
  $st = $pdo->query($sql." ORDER BY b.check_in DESC, b.created_at DESC, b.id DESC");
} else {
  $sql .= " WHERE b.user_id=? ";
  $st = $pdo->prepare($sql." ORDER BY b.check_in DESC, b.created_at DESC, b.id DESC");
  $st->execute([$uid]);
}
$bookings = $st->fetchAll(PDO::FETCH_ASSOC);

// helper to format dt
function dt($s) {
  if (!$s) return '';
  // If already datetime, show with time; else show date only
  return (strlen($s) > 10) ? $s : $s.' 00:00:00';
}

require_once __DIR__.'/header.php';
?>
<section class="container">
  <h1 class="h2" style="margin:0 0 12px">My Bookings</h1>

  <?php if ($flash): ?>
    <div class="flash" style="margin:10px 0"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="flash error" style="margin:10px 0"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$bookings): ?>
    <div class="card"><p class="muted">No bookings yet.</p></div>
  <?php else: ?>
  <div class="card" style="overflow-x:auto">
    <table class="table" style="width:100%;border-collapse:collapse">
      <thead>
        <tr>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line)">#</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line)">Room Type</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line)">Assigned Room #</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line)">Check-in</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line)">Check-out</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line)">Nights</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line)">Status</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);width:260px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $today = new DateTime('today');
        foreach ($bookings as $b):
          $ci = new DateTime(substr($b['check_in'], 0, 10));
          $co = new DateTime(substr($b['check_out'], 0, 10));
          $nights = max(1, (int)$ci->diff($co)->days);
          $status = $b['status'];
          $can_extend = ($status==='confirmed');
          $can_cancel = ($status!=='cancelled'); // allow cancel anytime; tweak if you want time cutoff
          // min date for extend: max(today, current check-out)
          $minForExtend = max($today->format('Y-m-d'), $co->format('Y-m-d'));
        ?>
        <tr>
          <td style="padding:10px;border-top:1px solid var(--line)">#<?= (int)$b['id'] ?></td>
          <td style="padding:10px;border-top:1px solid var(--line)"><?= htmlspecialchars($b['room_type']) ?></td>
          <td style="padding:10px;border-top:1px solid var(--line)"><?= $b['unit_number'] ? (int)$b['unit_number'] : '<span class="muted">TBD</span>' ?></td>
          <td style="padding:10px;border-top:1px solid var(--line)"><?= htmlspecialchars(dt($b['check_in'])) ?></td>
          <td style="padding:10px;border-top:1px solid var(--line)"><?= htmlspecialchars(dt($b['check_out'])) ?></td>
          <td style="padding:10px;border-top:1px solid var(--line)"><?= $nights ?></td>
          <td style="padding:10px;border-top:1px solid var(--line)"><?= htmlspecialchars($status) ?></td>
          <td style="padding:10px;border-top:1px solid var(--line)">
            <?php if ($can_extend): ?>
              <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="extend_booking_id" value="<?= (int)$b['id'] ?>">
                <div>
                  <label class="tiny muted" style="display:block;margin-bottom:4px">New check-out</label>
                  <input class="input extend-date"
                         name="new_check_out"
                         placeholder="yyyy-mm-dd"
                         data-min="<?= htmlspecialchars($minForExtend) ?>"
                         style="width:140px">
                </div>
                <button class="btn" type="submit">Extend</button>
              </form>
            <?php else: ?>
              <span class="muted">No actions</span>
            <?php endif; ?>

            <?php if ($can_cancel): ?>
              <form method="post" style="margin-top:8px">
                <input type="hidden" name="cancel_booking_id" value="<?= (int)$b['id'] ?>">
                <button class="btn" type="submit" onclick="return confirm('Cancel this booking?');">Cancel</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Init Flatpickr on each extend field
document.querySelectorAll('.extend-date').forEach(inp=>{
  const min = inp.dataset.min; // computed server-side: max(today, current checkout)
  flatpickr(inp, {
    dateFormat: "Y-m-d",
    minDate: min,   // no past dates + must be after current checkout
    allowInput: false
  });
});
</script>

<?php require_once __DIR__.'/footer.php'; ?>