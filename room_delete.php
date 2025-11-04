```php
<?php require 'db.php'; require 'auth.php'; require_role(['admin','staff']);
$id = (int)($_GET['id'] ?? 0);
$pdo->prepare("DELETE FROM rooms WHERE id=?")->execute([$id]);
header("Location: rooms_list.php");
