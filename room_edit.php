<?php
// room_edit.php — Admin/staff can edit a room
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_role(['admin','staff']);
require_once __DIR__.'/header.php';

// ---- helpers ----
function column_exists(PDO $pdo, string $table, string $column): bool {
  static $cache = [];
  $key = $table.'.'.$column;
  if (isset($cache[$key])) return $cache[$key];
  $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
  $st->execute([$column]);
  return $cache[$key] = (bool)$st->fetch();
}

// ---- load room ----
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo "<section class='container'><div class='card'>Invalid room id.</div></section>"; require 'footer.php'; exit; }

$st = $pdo->prepare("SELECT * FROM rooms WHERE id=?");
$st->execute([$id]);
$room = $st->fetch(PDO::FETCH_ASSOC);
if (!$room) { echo "<section class='container'><div class='card'>Room not found.</div></section>"; require 'footer.php'; exit; }

// Does the table have an inventory column?
$hasInventory = column_exists($pdo, 'rooms', 'inventory');

$err = $ok = '';

// ---- handle POST (save) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $number = trim($_POST['number'] ?? '');
  $type   = trim($_POST['type'] ?? '');
  $rate   = (float)($_POST['rate'] ?? 0);
  $max    = (int)($_POST['max_guests'] ?? 1);
  $img    = trim($_POST['image_url'] ?? '');
  $desc   = trim($_POST['description'] ?? '');
  $active = isset($_POST['is_active']) ? 1 : 0;
  $inv    = $hasInventory ? max(1, (int)($_POST['inventory'] ?? 1)) : null;

  if ($number === '' || $type === '' || $rate <= 0 || $max < 1) {
    $err = 'Please fill in number, type, a positive rate, and max guests ≥ 1.';
  } else {
    try {
      if ($hasInventory) {
        $sql = "UPDATE rooms
                   SET number=?, type=?, image_url=?, description=?, rate_cents=?, max_guests=?, is_active=?, inventory=?
                 WHERE id=?";
        $params = [$number, $type, $img ?: null, $desc ?: null, (int)round($rate*100), $max, $active, $inv, $id];
      } else {
        $sql = "UPDATE rooms
                   SET number=?, type=?, image_url=?, description=?, rate_cents=?, max_guests=?, is_active=?
                 WHERE id=?";
        $params = [$number, $type, $img ?: null, $desc ?: null, (int)round($rate*100), $max, $active, $id];
      }
      $up = $pdo->prepare($sql);
      $up->execute($params);
      $ok = 'Room updated successfully.';
      // refresh loaded room
      $st = $pdo->prepare("SELECT * FROM rooms WHERE id=?");
      $st->execute([$id]);
      $room = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      $err = 'Update failed: '.$e->getMessage();
    }
  }
}

// Nicely formatted values for the form
$rate_display = number_format(((int)$room['rate_cents'])/100, 2, '.', '');
?>
<section class="container" style="padding-top:20px">
  <div class="card" style="max-width:860px;margin:0 auto">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px">
      <h2 class="h2" style="margin:0">Edit Room #<?= htmlspecialchars($room['number']) ?></h2>
      <div>
        <a class="btn" href="rooms_list.php">Back to Rooms</a>
        <?php if (!empty($room['id'])): ?>
          <a class="btn ghost" href="room.php?id=<?= (int)$room['id'] ?>">View</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($err): ?>
      <div class="flash error" style="margin:12px 0"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="flash" style="margin:12px 0"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>

    <form method="post" class="grid" style="margin-top:10px">
      <div class="span-3">
        <label class="tiny muted">Room number</label>
        <input class="input" name="number" value="<?= htmlspecialchars($room['number']) ?>" required>
      </div>

      <div class="span-3">
        <label class="tiny muted">Type</label>
        <input class="input" name="type" value="<?= htmlspecialchars($room['type']) ?>" placeholder="Queen, King, Suite" required>
      </div>

      <div class="span-3">
        <label class="tiny muted">Rate (USD / night)</label>
        <input class="input" type="number" step="0.01" min="0.01" name="rate" value="<?= htmlspecialchars($rate_display) ?>" required>
      </div>

      <div class="span-3">
        <label class="tiny muted">Max guests</label>
        <input class="input" type="number" min="1" name="max_guests" value="<?= (int)$room['max_guests'] ?>" required>
      </div>

      <?php if ($hasInventory): ?>
      <div class="span-3">
        <label class="tiny muted">Inventory (simultaneous stays)</label>
        <input class="input" type="number" min="1" name="inventory" value="<?= (int)($room['inventory'] ?? 1) ?>">
        <div class="tiny muted">How many overlapping bookings allowed for this room/type.</div>
      </div>
      <?php endif; ?>

      <div class="span-9">
        <label class="tiny muted">Image URL</label>
        <input class="input" name="image_url" value="<?= htmlspecialchars($room['image_url'] ?? '') ?>" placeholder="https://...">
      </div>

      <div class="span-12">
        <label class="tiny muted">Description</label>
        <textarea class="input" name="description" rows="4" placeholder="Short description..."><?= htmlspecialchars($room['description'] ?? '') ?></textarea>
      </div>

      <div class="span-6" style="display:flex;align-items:center;gap:10px">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" <?= !empty($room['is_active']) ? 'checked' : '' ?>>
          <span>Active (bookable)</span>
        </label>
      </div>

      <div class="span-12" style="margin-top:6px">
        <button class="btn primary" type="submit">Save changes</button>
        <a class="btn" href="rooms_list.php" style="margin-left:8px">Cancel</a>
      </div>
    </form>
  </div>

  <?php if (!empty($room['image_url'])): ?>
    <div class="card" style="max-width:860px;margin:16px auto;padding:0;overflow:hidden">
      <img src="<?= htmlspecialchars($room['image_url']) ?>" alt="Preview" style="width:100%;max-height:380px;object-fit:cover">
    </div>
  <?php endif; ?>
</section>
<?php require_once __DIR__.'/footer.php'; ?>