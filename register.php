<?php require 'db.php'; require 'header.php'; ?>
<?php
$err = $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pw = $_POST['password'] ?? '';

  if (!$name || !$email || !$pw) {
    $err = 'All fields are required.';
  } else {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $err = 'Email already in use.';
    else {
      $hash = password_hash($pw, PASSWORD_DEFAULT);
      $pdo->prepare("INSERT INTO users (name,email,password_hash) VALUES (?,?,?)")
          ->execute([$name,$email,$hash]);
      $ok = 'Account created. You can now log in.';
    }
  }
}
?>
<div class="card">
  <h2>Create account</h2>
  <?php if($err): ?><div class="flash"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if($ok): ?><div class="flash"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
  <form method="post">
    <div class="row">
      <div><label>Name<input class="input" name="name"></label></div>
      <div><label>Email<input class="input" name="email" type="email"></label></div>
    </div>
    <label>Password<input class="input" name="password" type="password"></label>
    <br><button class="btn">Register</button>
  </form>
</div>
<?php require 'footer.php'; ?>
