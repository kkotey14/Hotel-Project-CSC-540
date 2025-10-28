<?php
// admin_bookings.php — full admin control over bookings (search, filters, actions)
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_role(['admin','staff']);
require_once __DIR__.'/header.php';

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function badge($status){
  $map = [
    'confirmed' => ['Confirmed','#0f5132','#d1e7dd','#badbcc'],
    'pending'   => ['Pending','#664d03','#fff3cd','#ffe69c'],
    'cancelled' => ['Cancelled','#842029','#f8d7da','#f1aeb5'],
  ];
  $x = $map[$status] ?? ['Unknown','#333','#eee','#ddd'];
  [$label,$fg,$bg,$bd] = $x;
  return '<span class="tiny" style="display:inline-block;padding:.2rem .5rem;border-radius:.5rem;background:'.$bg.';border:1px solid '.$bd.';color:'.$fg.'">'.$label.'</span>';
}

/* ---------- Actions (Confirm / Cancel / Delete) ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'], $_POST['booking_id'])) {
  $bid = (int)$_POST['booking_id'];
  switch ($_POST['action']) {
    case 'confirm':
      $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=?")->execute([$bid]);
      break;
    case 'cancel':
      $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$bid]);
      break;
    case 'delete':
      // hard delete
      $pdo->prepare("DELETE FROM bookings WHERE id=?")->execute([$bid]);
      break;
  }
  // keep filters on redirect
  $qs = $_SERVER['QUERY_STRING'] ? ('?'.$_SERVER['QUERY_STRING']) : '';
  header("Location: admin_bookings.php".$qs); exit;
}

/* ---------- Filters ---------- */
// tabs: view = all | upcoming | current | past | cancelled
$view = $_GET['view'] ?? 'all';
$q     = trim($_GET['q'] ?? '');
$from  = trim($_GET['from'] ?? '');
$to    = trim($_GET['to'] ?? '');

// build WHERE
$where = [];
$params = [];

// tab filters
if ($view === 'upcoming') {
  $where[] = "b.check_in >= CURDATE() AND b.status <> 'cancelled'";
} elseif ($view === 'current') {
  $where[] = "CURDATE() >= b.check_in AND CURDATE() < b.check_out AND b.status <> 'cancelled'";
} elseif ($view === 'past') {
  $where[] = "b.check_out < CURDATE()";
} elseif ($view === 'cancelled') {
  $where[] = "b.status = 'cancelled'";
}

// date range for check_in
if ($from !== '') { $where[] = "b.check_in >= ?"; $params[] = $from; }
if ($to   !== '') { $where[] = "b.check_in <= ?"; $params[] = $to; }

// text search across id, name, email, room number, type
if ($q !== '') {
  $where[] = "(u.name LIKE ? OR u.email LIKE ? OR r.number LIKE ? OR r.type LIKE ? OR b.id = ?)";
  $like = '%'.$q.'%';
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $params[] = ctype_digit($q) ? (int)$q : 0;
}

$sql = "
  SELECT
    b.id           AS booking_id,
    b.status,
    b.check_in,
    b.check_out,
    b.created_at,
    b.unit_number,
    TIMESTAMPDIFF(DAY, b.check_in, b.check_out) AS nights,
    u.id           AS user_id,
    u.name         AS user_name,
    u.email        AS user_email,
    r.id           AS room_id,
    r.type         AS room_type,
    r.number       AS room_number,
    r.inventory    AS room_inventory
  FROM bookings b
  JOIN users  u ON u.id = b.user_id
  JOIN rooms  r ON r.id = b.room_id
  ".( $where ? ("WHERE ".implode(" AND ", $where)) : "" )."
  ORDER BY b.created_at DESC
  LIMIT 100
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// quick counters for header cards
$stats = [
  'rooms'     => (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),
  'bookings'  => (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
  'upcoming'  => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE check_in >= CURDATE() AND status <> 'cancelled'")->fetchColumn(),
  'customers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
];

// helper to build tab link
function tabHref($v){
  $qs = $_GET;
  $qs['view'] = $v;
  return 'admin_bookings.php?'.http_build_query($qs);
}
?>
<section class="container">
  <div class="grid" style="margin-bottom:16px">
    <div class="span-3 card center">
      <div class="h2" style="margin:0"><?= $stats['rooms'] ?></div>
      <div class="muted tiny">Rooms</div>
    </div>
    <div class="span-3 card center">
      <div class="h2" style="margin:0"><?= $stats['bookings'] ?></div>
      <div class="muted tiny">Bookings</div>
    </div>
    <div class="span-3 card center">
      <div class="h2" style="margin:0"><?= $stats['upcoming'] ?></div>
      <div class="muted tiny">Upcoming</div>
    </div>
    <div class="span-3 card center">
      <div class="h2" style="margin:0"><?= $stats['customers'] ?></div>
      <div class="muted tiny">Customers</div>
    </div>
  </div>

  <div class="card" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;justify-content:space-between">
      <!-- Tabs -->
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php
          $tabs = ['all'=>'All','upcoming'=>'Upcoming','current'=>'Current','past'=>'Past','cancelled'=>'Cancelled'];
          foreach ($tabs as $val=>$label):
            $is = ($view===$val);
        ?>
          <a class="btn <?= $is?'primary':'' ?>" href="<?= h(tabHref($val)) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </div>

      <!-- Filters -->
      <form method="get" style="display:flex;gap:8px;align-items:end">
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <label>From
          <input class="input" type="date" name="from" value="<?= h($from) ?>">
        </label>
        <label>To
          <input class="input" type="date" name="to" value="<?= h($to) ?>">
        </label>
        <label style="min-width:240px">Search
          <input class="input" type="text" name="q" placeholder="Name, email, room #, type or ID"
                 value="<?= h($q) ?>">
        </label>
        <button class="btn">Apply</button>
        <a class="btn" href="admin_bookings.php">Reset</a>
      </form>
    </div>
  </div>

  <div class="card" style="overflow:auto">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Guest</th>
          <th>Email</th>
          <th>Room</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Nights</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="10" class="muted">No bookings found for the selected filters.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>#<?= (int)$r['booking_id'] ?></td>
          <td><?= h($r['user_name']) ?></td>
          <td><?= h($r['user_email']) ?></td>
          <td><?= h($r['room_type']) ?> · <?= (int)$r['room_number'] ?><?= $r['unit_number'] ? ' · unit '.$r['unit_number'] : '' ?></td>
          <td><?= h($r['check_in']) ?></td>
          <td><?= h($r['check_out']) ?></td>
          <td><?= (int)$r['nights'] ?></td>
          <td><?= badge($r['status']) ?></td>
          <td class="tiny"><?= h($r['created_at']) ?></td>
          <td style="white-space:nowrap">
            <form method="post" style="display:inline" onsubmit="return confirm('Cancel this booking?');">
              <input type="hidden" name="booking_id" value="<?= (int)$r['booking_id'] ?>">
              <input type="hidden" name="action" value="cancel">
              <button class="btn tiny">Cancel</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete permanently?');">
              <input type="hidden" name="booking_id" value="<?= (int)$r['booking_id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button class="btn tiny">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require_once __DIR__.'/footer.php'; ?>