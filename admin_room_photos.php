<?php
require 'db.php'; require 'auth.php'; require_role(['admin','staff']); require 'header.php';

$room_id = (int)($_GET['room_id'] ?? 0);
$roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE id=?");
$roomStmt->execute([$room_id]);
$room = $roomStmt->fetch(PDO::FETCH_ASSOC);
if (!$room) { echo "<section class='card'><h2>Room not found.</h2></section>"; require 'footer.php'; exit; }

$msg = '';

// Handle add (upload or URL)
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $type = in_array($_POST['photo_type'] ?? 'main', ['main','bathroom','other']) ? $_POST['photo_type'] : 'main';
  $caption = trim($_POST['caption'] ?? '');

  // Upload file
  if (!empty($_FILES['image_file']['name'])) {
    $f = $_FILES['image_file'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
        if (!is_dir('uploads')) mkdir('uploads', 0775, true);
        $dest = 'uploads/room'.$room_id.'_'.time().'.'.$ext;
        if (move_uploaded_file($f['tmp_name'], $dest)) {
          $pdo->prepare("INSERT INTO room_photos (room_id, url, photo_type, caption) VALUES (?,?,?,?)")
              ->execute([$room_id, $dest, $type, $caption ?: null]);
          $msg = 'Uploaded.';
        } else $msg = 'Upload failed.';
      } else $msg = 'Unsupported type.';
    } else $msg = 'Upload error.';
  }

  // Or add by URL
  $image_url = trim($_POST['image_url'] ?? '');
  if ($image_url) {
    $pdo->prepare("INSERT INTO room_photos (room_id, url, photo_type, caption) VALUES (?,?,?,?)")
        ->execute([$room_id, $image_url, $type, $caption ?: null]);
    $msg = $msg ? ($msg.' Added URL.') : 'Added URL.';
  }
}

// Delete
if (isset($_GET['delete'])) {
  $pid = (int)$_GET['delete'];
  $pdo->prepare("DELETE FROM room_photos WHERE id=? AND room_id=?")->execute([$pid, $room_id]);
  $msg = 'Deleted.';
}

// Fetch by type for tabs
$photosAllStmt = $pdo->prepare("SELECT * FROM room_photos WHERE room_id=? ORDER BY id DESC");
$photosAllStmt->execute([$room_id]);
$photos = $photosAllStmt->fetchAll(PDO::FETCH_ASSOC);

function filterByType($items, $type){
  return array_values(array_filter($items, fn($x)=>$x['photo_type']===$type));
}
$mainPhotos = filterByType($photos, 'main');
$bathPhotos = filterByType($photos, 'bathroom');
$otherPhotos = filterByType($photos, 'other');
?>

<section class="container">
  <div class="card" style="padding:20px">
    <h2 class="h2">Manage Photos — Room <?= htmlspecialchars($room['number']) ?> (<?= htmlspecialchars($room['type']) ?>)</h2>
    <p class="muted">Upload or link images; choose a type (“Main”, “Bathroom”, or “Other”).</p>
    <?php if($msg): ?><div class="flash" style="margin-top:10px"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="grid" style="margin-top:14px">
      <div class="span-4">
        <label>Upload image
          <input class="input" type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp,.gif">
        </label>
      </div>
      <div class="span-4">
        <label>Or image URL
          <input class="input" name="image_url" placeholder="https://images.pexels.com/...">
        </label>
      </div>
      <div class="span-2">
        <label>Type
          <select class="input" name="photo_type">
            <option value="main">Main</option>
            <option value="bathroom">Bathroom</option>
            <option value="other">Other</option>
          </select>
        </label>
      </div>
      <div class="span-12">
        <label>Caption (optional)
          <input class="input" name="caption" maxlength="140" placeholder="Short description (max 140 chars)">
        </label>
      </div>
      <div class="span-12">
        <button class="btn primary">Add Photo</button>
        <a class="btn" href="room.php?id=<?= $room_id ?>" style="margin-left:8px">View Room</a>
      </div>
    </form>
  </div>
</section>

<section class="container">
  <div class="card" style="padding:18px">
    <h3 class="h3">Main Photos</h3>
    <div class="grid" style="margin-top:10px">
      <?php foreach($mainPhotos as $p): ?>
        <div class="span-4 card" style="padding:10px">
          <img src="<?= htmlspecialchars($p['url']) ?>" alt="Photo" style="width:100%;height:180px;object-fit:cover;border-radius:10px;border:1px solid var(--line)">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
            <small class="muted"><?= htmlspecialchars($p['caption'] ?? '') ?></small>
            <a class="btn" href="?room_id=<?= $room_id ?>&delete=<?= $p['id'] ?>" onclick="return confirm('Delete this photo?')">Delete</a>
          </div>
        </div>
      <?php endforeach; if(!$mainPhotos): ?><p class="muted">No photos yet.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="container">
  <div class="card" style="padding:18px">
    <h3 class="h3">Bathroom Photos</h3>
    <div class="grid" style="margin-top:10px">
      <?php foreach($bathPhotos as $p): ?>
        <div class="span-4 card" style="padding:10px">
          <img src="<?= htmlspecialchars($p['url']) ?>" alt="Bathroom" style="width:100%;height:180px;object-fit:cover;border-radius:10px;border:1px solid var(--line)">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
            <small class="muted"><?= htmlspecialchars($p['caption'] ?? '') ?></small>
            <a class="btn" href="?room_id=<?= $room_id ?>&delete=<?= $p['id'] ?>" onclick="return confirm('Delete this photo?')">Delete</a>
          </div>
        </div>
      <?php endforeach; if(!$bathPhotos): ?><p class="muted">No bathroom photos yet.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="container">
  <div class="card" style="padding:18px">
    <h3 class="h3">Other Photos</h3>
    <div class="grid" style="margin-top:10px">
      <?php foreach($otherPhotos as $p): ?>
        <div class="span-4 card" style="padding:10px">
          <img src="<?= htmlspecialchars($p['url']) ?>" alt="Other" style="width:100%;height:180px;object-fit:cover;border-radius:10px;border:1px solid var(--line)">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
            <small class="muted"><?= htmlspecialchars($p['caption'] ?? '') ?></small>
            <a class="btn" href="?room_id=<?= $room_id ?>&delete=<?= $p['id'] ?>" onclick="return confirm('Delete this photo?')">Delete</a>
          </div>
        </div>
      <?php endforeach; if(!$otherPhotos): ?><p class="muted">No other photos yet.</p><?php endif; ?>
    </div>
  </div>
</section>

<?php require 'footer.php'; ?>
