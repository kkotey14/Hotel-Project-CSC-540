<?php
require 'db.php';
require 'header.php';

$token = trim($_GET['token'] ?? '');
$err = $ok = '';
$valid = false;
$resetRow = null;

if ($token) {
  $st = $pdo->prepare("SELECT pr.id, pr.user_id, pr.token, pr.expires_at, pr.used, u.email, u.name 
                       FROM password_resets pr 
                       JOIN users u ON u.id = pr.user_id
                       WHERE pr.token=? LIMIT 1");
  $st->execute([$token]);
  $resetRow = $st->fetch(PDO::FETCH_ASSOC);

  if ($resetRow) {
    $now = new DateTime();
    $exp = new DateTime($resetRow['expires_at']);
    if (!$resetRow['used'] && $now <= $exp) {
      $valid = true;
    } else {
      $err = 'This reset link is invalid or has expired.';
    }
  } else {
    $err = 'This reset link is invalid or has expired.';
  }
} else {
  $err = 'Missing reset token.';
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = trim($_POST['token'] ?? '');
  $new1  = $_POST['password'] ?? '';
  $new2  = $_POST['password2'] ?? '';

  if (!$new1 || strlen($new1) < 8) {
    $err = 'Password must be at least 8 characters.';
  } elseif ($new1 !== $new2) {
    $err = 'Passwords do not match.';
  } else {
    // Validate token again on submit
    $st = $pdo->prepare("SELECT id, user_id, expires_at, used FROM password_resets WHERE token=? LIMIT 1");
    $st->execute([$token]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $now = new DateTime();
      $exp = new DateTime($row['expires_at']);
      if (!$row['used'] && $now <= $exp) {
        // Update user password
        $hash = password_hash($new1, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $row['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([$row['id']]);
        // (Optional) Invalidate other outstanding tokens for the user
        $pdo->prepare("UPDATE password_resets SET used=1 WHERE user_id=? AND id<>?")->execute([$row['user_id'], $row['id']]);
        $pdo->commit();

        $ok = 'Your password has been updated. You can now sign in.';
        $valid = false; // stop showing the form
      } else {
        $err = 'This reset link is invalid or has expired.';
      }
    } else {
      $err = 'This reset link is invalid or has expired.';
    }
  }
}
?>
<section class="container" style="padding-top:28px">
  <div class="card" style="max-width:560px;margin:0 auto;">
    <h2 class="h2" style="margin:0 0 8px">Reset password</h2>

    <?php if ($err): ?>
      <div class="flash error" style="margin:12px 0"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="flash" style="margin:12px 0"><?= htmlspecialchars($ok) ?></div>
      <p class="muted tiny"><a href="login.php">Go to login</a></p>
    <?php endif; ?>

    <?php if ($valid && !$ok): ?>
      <p class="muted" style="margin-top:0">Choose a new password for your account.</p>
      <form method="post" style="margin-top:8px">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div style="margin-bottom:10px">
          <label class="tiny muted">New password</label>
          <input class="input" type="password" name="password" minlength="8" required>
        </div>
        <div style="margin-bottom:10px">
          <label class="tiny muted">Confirm new password</label>
          <input class="input" type="password" name="password2" minlength="8" required>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:12px">
          <button class="btn primary" type="submit">Update password</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require 'footer.php'; ?>