<?php
require 'db.php';
require 'auth.php';

if (!empty($_SESSION['user']['id'])) {
  $uid = (int)$_SESSION['user']['id'];
  // Mark the most recent open session as logged out
  $stmt = $pdo->prepare("
    UPDATE user_sessions
       SET logout_at = CURRENT_TIMESTAMP
     WHERE user_id = ?
       AND logout_at IS NULL
     ORDER BY login_at DESC
     LIMIT 1
  ");
  $stmt->execute([$uid]);
}

session_destroy();
header("Location: index.php");
exit;