<?php
require 'db.php';
require 'auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$err = '';

// If user is already logged in, go to Stay page
if (!empty($_SESSION['user'])) {
  $to = $_GET['next'] ?? ($_SESSION['redirect_after_login'] ?? 'rooms_list.php');
  unset($_SESSION['redirect_after_login']);
  header("Location: {$to}");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pw    = $_POST['password'] ?? '';

  // Basic validation
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Please enter a valid email address.';
  } elseif ($pw === '') {
    $err = 'Password is required.';
  } else {
    // Fetch user
    $stmt = $pdo->prepare("
      SELECT id, name, email, role, password_hash
      FROM users
      WHERE email = ?
      LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pw, $user['password_hash'])) {
      // 1) Set session
      $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
      ];

      // 2) Log this session (login_at is defaulted in DB)
      try {
        $insSess = $pdo->prepare("
          INSERT INTO user_sessions (user_id, ip, user_agent)
          VALUES (?, ?, ?)
        ");
        $insSess->execute([
          $user['id'],
          $_SERVER['REMOTE_ADDR'] ?? null,
          substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
      } catch (Throwable $e) {
        // Don’t block login if logging fails; just record it
        error_log('user_sessions insert failed: '.$e->getMessage());
      }

      // 3) Smart redirect
      $redirect = $_GET['next'] ?? ($_SESSION['redirect_after_login'] ?? 'rooms_list.php');
      unset($_SESSION['redirect_after_login']);

      header("Location: {$redirect}");
      exit;
    } else {
      $err = 'Invalid email or password.';
    }
  }
}

// Render page AFTER handling POST so redirects above are clean
require 'header.php';
?>
<section class="container" style="padding-top:28px">
  <div class="card" style="max-width:560px;margin:0 auto;">
    <h2 class="h2" style="margin:0 0 8px">Sign in</h2>
    <p class="muted" style="margin-top:0">Welcome back to The Riverside.</p>

    <?php if ($err): ?>
      <div class="flash error" style="margin:12px 0">
        <?= htmlspecialchars($err) ?>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="on" style="margin-top:8px">
      <div style="margin-bottom:12px">
        <label class="tiny muted">Email</label>
        <input class="input" type="email" name="email" required autofocus>
      </div>

      <div style="margin-bottom:6px">
        <label class="tiny muted">Password</label>
        <input class="input" type="password" name="password" required>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin:8px 0 14px">
        <span class="tiny">
          <a href="forgot_password.php">Forgot password?</a>
        </span>
        <button class="btn primary" type="submit">Login</button>
      </div>
    </form>

    <div class="muted tiny" style="border-top:1px solid var(--line);padding-top:12px;margin-top:6px">
      No account? <a href="register.php">Create one</a>
    </div>
  </div>
</section>
<?php require 'footer.php'; ?>