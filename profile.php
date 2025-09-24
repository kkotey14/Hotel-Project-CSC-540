<?php require 'db.php'; require 'auth.php'; require_login(); require 'header.php'; ?>
<div class="card">
  <h2>My Profile</h2>
  <p><b>Name:</b> <?= htmlspecialchars($_SESSION['user']['name']) ?></p>
  <p><b>Email:</b> <?= htmlspecialchars($_SESSION['user']['email']) ?></p>
  <p><b>Role:</b> <?= htmlspecialchars($_SESSION['user']['role']) ?></p>
</div>
<?php require 'footer.php'; ?>
