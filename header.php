<?php if (session_status() === PHP_SESSION_NONE) session_start(); require_once 'config.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(HOTEL_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body class="site">  <!-- flex column wrapper -->
<header class="site-header">
  <div class="container header-row">
    <a href="index.php" class="brand">
      <span class="brand-mark">the</span>
      <span class="brand-name"><?= htmlspecialchars(HOTEL_NAME) ?></span>
    </a>
    <nav class="main-nav">
      <a href="index.php">Stay</a>
      <a href="index.php#amenities" class="hide-mobile">Amenities</a>
      <a href="rooms_list.php">Rooms</a>
      <a href="index.php#dine" class="hide-mobile">Dine</a>
      <a href="index.php#explore" class="hide-mobile">Explore</a>
      <?php if (!empty($_SESSION['user'])): ?>
        <a href="my_bookings.php" class="hide-mobile">My Bookings</a>
        <?php if ($_SESSION['user']['role'] !== 'customer'): ?><a href="admin_dashboard.php" class="hide-mobile">Admin</a><?php endif; ?>
        <a href="logout.php" class="pill">Logout</a>
      <?php else: ?>
        <a href="login.php" class="hide-mobile">Login</a>
      <?php endif; ?>
      <a href="rooms_list.php" class="btn-cta">Book Now</a>
    </nav>
  </div>
</header>

<main class="site-main">  <!-- grows to push footer down -->
