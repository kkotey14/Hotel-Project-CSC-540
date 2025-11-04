<?php
require 'db.php';
require 'auth.php';
require_login();

// Only admin/staff allowed
if (!in_array($_SESSION['user']['role'], ['admin','staff'])) {
    header("Location: index.php");
    exit;
}

$room_id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id=?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    die("Room not found.");
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_limit = (int)$_POST['max_guests'];
    if ($new_limit > 0) {
        $upd = $pdo->prepare("UPDATE rooms SET max_guests=? WHERE id=?");
        $upd->execute([$new_limit, $room_id]);
        $msg = "Guest limit updated successfully!";
        $room['max_guests'] = $new_limit; // refresh value
    } else {
        $msg = "Please enter a valid number greater than 0.";
    }
}
?>

<?php require 'header.php'; ?>
<div class="container card" style="padding:20px; max-width:600px; margin:20px auto;">
  <h2>Edit Guest Limit</h2>
  <p><strong>Room:</strong> <?= htmlspecialchars($room['type']) ?> — <?= htmlspecialchars($room['number']) ?></p>
  <?php if ($msg): ?><div class="flash"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  
  <form method="post">
    <label>Max Guests:
      <input type="number" name="max_guests" value="<?= htmlspecialchars($room['max_guests']) ?>" min="1" class="input" required>
    </label>
    <button type="submit" class="btn primary" style="margin-top:10px;">Save</button>
  </form>
  
  <div style="margin-top:10px;">
    <a class="btn" href="room.php?id=<?= $room['id'] ?>">Back to Room</a>
  </div>
</div>
<?php require 'footer.php'; ?>
