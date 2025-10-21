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
        <?php if ($_SESSION['user']['role'] !== 'customer'): ?>
          <a href="admin_dashboard.php" class="hide-mobile">Admin</a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>

    <!-- Right side: profile/avatar or login -->
    <div class="nav-right" style="display:flex;align-items:center;gap:12px">
      <?php if (!empty($_SESSION['user'])): 
        $u = $_SESSION['user'];
        $initial = strtoupper($u['name'] ? mb_substr($u['name'],0,1) : mb_substr($u['email'],0,1));
      ?>
        <a href="rooms_list.php" class="btn-cta hide-mobile">Book Now</a>

        <div class="profile-wrap" style="position:relative">
          <button id="avatarBtn" aria-haspopup="menu" aria-expanded="false"
            style="width:36px;height:36px;border-radius:50%;border:1px solid #d1d5db;
                   background:#e5e7eb;color:#111;display:flex;align-items:center;
                   justify-content:center;font-weight:700;cursor:pointer">
            <?= htmlspecialchars($initial) ?>
          </button>

          <div id="avatarMenu" role="menu" aria-label="User menu"
               style="position:absolute;right:0;top:46px;background:#fff;border:1px solid #e5e7eb;
                      border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,.08);width:220px;
                      padding:8px;display:none;z-index:1000">
            <div style="padding:8px 10px;border-bottom:1px solid #f1f1f1">
              <div style="font-weight:600"><?= htmlspecialchars($u['name'] ?: $u['email']) ?></div>
              <div class="muted" style="font-size:12px;"><?= htmlspecialchars($u['email']) ?></div>
            </div>
            
            <a class="menu-item" href="my_bookings.php">My Bookings</a>
            <?php if (in_array($u['role'], ['admin','staff'])): ?>
              <a class="menu-item" href="admin_dashboard.php">Admin</a>
            <?php endif; ?>
            <a class="menu-item" href="logout.php">Logout</a>
          </div>
        </div>

      <?php else: ?>
        <a href="login.php" class="hide-mobile">Login</a>
        <a href="register.php" class="hide-mobile">Register</a>
        <a href="rooms_list.php" class="btn-cta">Book Now</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<script>
(function(){
  const btn  = document.getElementById('avatarBtn');
  const menu = document.getElementById('avatarMenu');
  if (!btn || !menu) return;

  function closeMenu(){ menu.style.display='none'; btn.setAttribute('aria-expanded','false'); }
  function toggleMenu(e){
    e.stopPropagation();
    const open = menu.style.display === 'block';
    if (open) closeMenu(); else { menu.style.display='block'; btn.setAttribute('aria-expanded','true'); }
  }
  btn.addEventListener('click', toggleMenu);
  document.addEventListener('click', (e)=>{ if (!menu.contains(e.target) && e.target!==btn) closeMenu(); });
  document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeMenu(); });
})();
</script>

<main class="site-main">  <!-- grows to push footer down -->