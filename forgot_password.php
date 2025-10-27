<?php
require 'db.php';
require 'header.php';
require_once __DIR__.'/lib_mail.php';
require_once __DIR__.'/config.php';

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  if ($email) {
    // Look up the user
    $st = $pdo->prepare("SELECT id, name, email FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    // Always respond the same to avoid email enumeration
    $notice = 'If that address exists, we sent a reset link. Please check your inbox.';

    if ($u) {
      // Create token valid for 1 hour
      $token = bin2hex(random_bytes(32)); // 64 chars
      $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
      $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)");
      $ins->execute([$u['id'], $token, $expires]);

      // Absolute link
      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $base   = $scheme.'://'.$_SERVER['HTTP_HOST'].(defined('BASE_URL') ? BASE_URL : '');
      $link   = $base.'/reset_password.php?token='.urlencode($token);

      // Send mail (Mailtrap in dev)
      $subject = 'Password Reset — '.(defined('HOTEL_NAME') ? HOTEL_NAME : 'HotelApp');
      $html = '
        <div style="font-family:Inter,Arial,sans-serif;max-width:620px;margin:auto;padding:20px;border:1px solid #eee;border-radius:12px">
          <h2 style="margin:0 0 12px">'.htmlspecialchars(defined('HOTEL_NAME')?HOTEL_NAME:'HotelApp').'</h2>
          <p>Hello '.htmlspecialchars($u['name'] ?: $u['email']).',</p>
          <p>We received a request to reset your password. Click the button below to choose a new one. This link expires in 1 hour.</p>
          <p><a href="'.htmlspecialchars($link).'" style="display:inline-block;background:#8aa35f;color:#182411;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:600">Reset Password</a></p>
          <p class="muted" style="color:#6b7280">If you didn’t request this, you can ignore this email.</p>
        </div>';
      // Fire and forget
      @send_mail($u['email'], $subject, $html);
    }
  } else {
    $notice = 'Please enter your email address.';
  }
}
?>
<section class="container" style="padding-top:28px">
  <div class="card" style="max-width:560px;margin:0 auto;">
    <h2 class="h2" style="margin:0 0 8px">Forgot password</h2>
    <p class="muted" style="margin-top:0">Enter your email and we’ll send you a reset link.</p>

    <?php if ($notice): ?>
      <div class="flash" style="margin:12px 0"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <form method="post" style="margin-top:8px">
      <label class="tiny muted">Email</label>
      <input class="input" type="email" name="email" required autofocus>
      <div style="display:flex;justify-content:flex-end;margin-top:12px">
        <button class="btn primary" type="submit">Send reset link</button>
      </div>
    </form>

    <div class="muted tiny" style="border-top:1px solid var(--line);padding-top:12px;margin-top:12px">
      Remembered it? <a href="login.php">Back to sign in</a>
    </div>
  </div>
</section>
<?php require 'footer.php'; ?>