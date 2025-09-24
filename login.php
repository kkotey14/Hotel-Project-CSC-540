<?php require 'db.php'; require 'auth.php'; require 'header.php'; ?>
<?php
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pw = $_POST['password'] ?? '';
  $stmt = $pdo->prepare("SELECT id,name,email,role,password_hash FROM users WHERE email=?");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($user && password_verify($pw, $user['password_hash'])) {
    $_SESSION['user'] = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']];
    header("Location: index.php"); exit;
  } else $err = 'Invalid credentials.';
}
?>
<div class="card">
  <h2>Login</h2>
  <?php if($err): ?><div class="flash"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="post">
    <label>Email<input class="input" name="email" type="email"></label>
    <label>Password<input class="input" name="password" type="password"></label>
    <br><button class="btn">Login</button>
  </form>
</div>
<?php require 'footer.php'; ?>
