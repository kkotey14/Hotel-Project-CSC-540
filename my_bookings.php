<?php
// my_bookings.php — staff can see guest info + search; customers see their own
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/header.php';

$user    = $_SESSION['user'];
$isStaff = in_array($user['role'] ?? 'customer', ['admin','staff'], true);

// ---------- Helpers ----------
function nights_between($a, $b) {
  try { $d1=new DateTime($a); $d2=new DateTime($b); return max(1, $d1->diff($d2)->days); }
  catch(Exception $e){ return 1; }
}
$today = new DateTime('today');

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  $bid    = (int)($_POST['booking_id'] ?? 0);

  if ($bid > 0) {
    if ($isStaff && in_array($action, ['approve','cancel'], true)) {
      $st = $pdo->prepare("SELECT id, status, check_in FROM bookings WHERE id=? LIMIT 1");
      $st->execute([$bid]);
      if ($bk = $st->fetch(PDO::FETCH_ASSOC)) {
        if ($action==='approve' && $bk['status']==='pending') {
          $up = $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=?");
          $up->execute([$bid]);
          header("Location: my_bookings.php?msg=approved");
          exit;
        }
        if ($action==='cancel' && in_array($bk['status'], ['pending','confirmed'], true)) {
          $up = $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
          $up->execute([$bid]);
          header("Location: my_bookings.php?msg=cancelled_staff");
          exit;
        }
      }
      header("Location: my_bookings.php?msg=not_allowed");
      exit;
    }

    if ($action==='cancel' && !$isStaff) {
      // Customer: cancel own confirmed future booking
      $st = $pdo->prepare("SELECT b.id, b.user_id, b.status, b.check_in
                           FROM bookings b WHERE b.id=? AND b.user_id=? LIMIT 1");
      $st->execute([$bid, $user['id']]);
      if ($bk = $st->fetch(PDO::FETCH_ASSOC)) {
        $ci = new DateTime($bk['check_in']);
        if ($bk['status']==='confirmed' && $ci > $today) {
          $up = $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
          $up->execute([$bid]);
          header("Location: my_bookings.php?msg=cancelled");
          exit;
        }
      }
      header("Location: my_bookings.php?msg=not_allowed");
      exit;
    }
  }
}

// ---------- Filters ----------
$view  = $_GET['view'] ?? 'upcoming'; // upcoming | past | cancelled | all | pending
$valid = ['upcoming','past','cancelled','all','pending'];
if (!in_array($view, $valid, true)) $view = 'upcoming';

// Optional staff search by guest or booking id
$q = '';
if ($isStaff) {
  $q = trim($_GET['q'] ?? '');
}

// ---------- Query ----------
$where  = [];
$params = [];

if (!$isStaff) { $where[] = "b.user_id = :uid"; $params[':uid'] = $user['id']; }

$todayStr = $today->format('Y-m-d');
switch ($view) {
  case 'upcoming':
    $where[] = "(b.status IN ('pending','confirmed') AND b.check_out >= :today)";
    $params[':today'] = $todayStr;
    break;
  case 'past':
    $where[] = "(b.check_out < :today)";
    $params[':today'] = $todayStr;
    break;
  case 'cancelled':
    $where[] = "b.status = 'cancelled'";
    break;
  case 'pending':
    $where[] = "b.status = 'pending'";
    break;
  case 'all':
  default:
    // none
    break;
}

if ($isStaff && $q !== '') {
  // search by guest name/email or booking id
  if (ctype_digit($q)) {
    $where[] = "b.id = :bid";
    $params[':bid'] = (int)$q;
  } else {
    $where[] = "(u.name LIKE :qq OR u.email LIKE :qq)";
    $params[':qq'] = '%'.$q.'%';
  }
}

$wsql = $where ? 'WHERE '.implode(' AND ', $where) : '';
$sql = "
SELECT
  b.id, b.user_id, b.room_id, b.check_in, b.check_out, b.status,
  r.number AS room_number, r.type AS room_type, r.image_url, r.rate_cents,
  u.name AS guest_name, u.email AS guest_email,
  (SELECT SUM(p.amount_cents) FROM payments p WHERE p.booking_id=b.id AND p.status='paid') AS paid_cents
FROM bookings b
JOIN rooms r ON r.id = b.room_id
JOIN users u ON u.id = b.user_id
$wsql
ORDER BY b.check_in DESC, b.id DESC
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// ---------- UI ----------
?>
<section class="container">
  <div class="card" style="padding:22px">
    <div class="grid" style="align-items:center">
      <div class="span-8">
        <h1 class="h2" style="margin:0 0 4px"><?= $isStaff ? 'All Bookings' : 'My Bookings' ?></h1>
        <p class="muted" style="margin:0">
          <?= $isStaff ? 'Filter, review guest details, and approve/cancel reservations.' : 'View and manage your stays.' ?>
        </p>
      </div>
      <div class="span-4" style="text-align:right">
        <div class="chips">
          <a class="chip<?= $view==='upcoming'?' chip-active':'' ?>" href="?view=upcoming">Upcoming</a>
          <a class="chip<?= $view==='pending'?' chip-active':'' ?>" href="?view=pending">Pending</a>
          <a class="chip<?= $view==='past'?' chip-active':'' ?>" href="?view=past">Past</a>
          <a class="chip<?= $view==='cancelled'?' chip-active':'' ?>" href="?view=cancelled">Cancelled</a>
          <a class="chip<?= $view==='all'?' chip-active':'' ?>" href="?view=all">All</a>
        </div>
      </div>
    </div>

    <?php if ($isStaff): ?>
      <form method="get" style="margin-top:12px">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
        <div class="grid" style="align-items:center">
          <div class="span-9">
            <input class="input" name="q" placeholder="Search by guest name, email, or booking #"
                   value="<?= htmlspecialchars($q) ?>">
          </div>
          <div class="span-3" style="text-align:right">
            <button class="btn">Search</button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php if (isset($_GET['msg'])): ?>
<section class="container">
  <div class="card" style="padding:14px;
    <?php
      $ok = in_array($_GET['msg'], ['approved','cancelled','cancelled_staff'], true);
      echo $ok ? 'color:#0f5132;background:#d1e7dd;border:1px solid #badbcc'
               : 'color:#842029;background:#f8d7da;border:1px solid #f5c2c7';
    ?>">
    <?php
      switch($_GET['msg']) {
        case 'approved':        echo 'Booking approved.'; break;
        case 'cancelled':       echo 'Booking cancelled.'; break;
        case 'cancelled_staff': echo 'Booking cancelled by staff.'; break;
        default:                echo 'Action not allowed.'; break;
      }
    ?>
  </div>
</section>
<?php endif; ?>

<section class="container">
  <?php if (!$rows): ?>
    <div class="card" style="padding:24px;text-align:center">
      <div class="h3" style="margin:0 0 8px">No bookings match your filter</div>
      <p class="muted" style="margin:0 0 14px">Try a different filter or clear the search.</p>
      <a class="btn primary" href="rooms_list.php">Find a room</a>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($rows as $b):
        $nights   = nights_between($b['check_in'], $b['check_out']);
        $fallback = $nights * (int)$b['rate_cents'];
        $totalCts = $b['paid_cents'] !== null ? (int)$b['paid_cents'] : $fallback;

        $ci = new DateTime($b['check_in']);
        $co = new DateTime($b['check_out']);
        $isUpcoming = $co >= $today;

        // badge color
        $badgeColor = '#6b7280';
        if ($b['status']==='pending')   $badgeColor = '#a16207';
        if ($b['status']==='confirmed') $badgeColor = '#2563eb';
        if ($b['status']==='cancelled') $badgeColor = '#dc2626';

        // permissions
        $customerCanCancel = !$isStaff && $b['status']==='confirmed' && $ci > $today;
        $staffCanApprove   = $isStaff && $b['status']==='pending';
        $staffCanCancel    = $isStaff && in_array($b['status'], ['pending','confirmed'], true);
      ?>
      <article class="span-6">
        <div class="card" style="overflow:hidden">
          <div class="grid">
            <div class="span-5">
              <div class="hero-img-wrap" style="height:180px">
                <img src="<?= htmlspecialchars($b['image_url'] ?: 'https://via.placeholder.com/800x600?text=Room') ?>"
                     alt="Room <?= htmlspecialchars($b['room_number']) ?>">
              </div>
            </div>
            <div class="span-7" style="padding:16px">
              <div class="h3" style="margin:0 0 6px">
                <?= htmlspecialchars($b['room_type']) ?> · Room <?= htmlspecialchars($b['room_number']) ?>
              </div>
              <div class="muted" style="margin-bottom:8px">
                <?= htmlspecialchars($b['check_in']) ?> → <?= htmlspecialchars($b['check_out']) ?>
                (<?= $nights ?> night<?= $nights>1?'s':'' ?>)
              </div>

              <?php if ($isStaff): ?>
                <div class="muted" style="margin-bottom:8px">
                  <strong>Guest:</strong>
                  <?= htmlspecialchars($b['guest_name'] ?: '(no name)') ?>
                  — <a href="mailto:<?= htmlspecialchars($b['guest_email']) ?>"><?= htmlspecialchars($b['guest_email']) ?></a>
                </div>
              <?php endif; ?>

              <div class="muted" style="margin-bottom:6px">Booking #<?= (int)$b['id'] ?></div>

              <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                <span class="chip" style="background:#eef2ff;color:#111;border:1px solid #e5e7eb">
                  $<?= number_format($totalCts/100, 2) ?> total
                </span>
                <span class="chip" style="border:1px solid #e5e7eb;color:#111">
                  <?= $isUpcoming ? 'Upcoming' : 'Completed' ?>
                </span>
                <span class="chip" style="border:1px solid #e5e7eb;color:#fff;background:<?= $badgeColor ?>">
                  <?= htmlspecialchars($b['status']) ?>
                </span>
              </div>

              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="btn" href="room.php?id=<?= (int)$b['room_id'] ?>">View room</a>
                <a class="btn ghost" href="rooms_list.php">Book again</a>

                <?php if ($customerCanCancel): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Cancel this booking?');">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <button class="btn danger" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca">
                      Cancel
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($staffCanApprove): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Approve this booking?');">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <button class="btn" style="background:#e0f2fe;border:1px solid #bae6fd;color:#075985">
                      Approve
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($staffCanCancel): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Cancel this booking?');">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <button class="btn danger" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca">
                      Cancel
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__.'/footer.php'; ?>