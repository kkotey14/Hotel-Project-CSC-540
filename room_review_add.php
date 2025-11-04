<?php
require 'db.php'; require 'auth.php'; require_login();
$room_id = (int)($_GET['id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
if ($room_id && $rating>=1 && $rating<=5) {
  $ins = $pdo->prepare("INSERT INTO reviews (room_id,user_id,rating,comment) VALUES (?,?,?,?)");
  $ins->execute([$room_id, $_SESSION['user']['id'], $rating, $comment ?: null]);
}
header("Location: room.php?id=".$room_id."#book");
