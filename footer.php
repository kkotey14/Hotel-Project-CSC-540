</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <div class="brand brand--footer">
        <span class="brand-name"><?= htmlspecialchars(HOTEL_NAME) ?></span>
      </div>
      <p class="muted">353 Main Ave, Norwalk, CT · reservations@example.com · (203) 555-0123</p>
    </div>

    <nav class="footer-links">
      <a href="index.php">Stay</a>
      <a href="rooms_list.php">Rooms</a>
      <a href="index.php#amenities">Amenities</a>
      <a href="index.php#dine">Dine</a>
      <a href="index.php#explore">Explore</a>
      <a href="privacy.php">Privacy</a>
    </nav>
  </div>

  <div class="container tiny muted">© <?= date('Y') ?> <?= htmlspecialchars(HOTEL_NAME) ?> · All rights reserved</div>
</footer>

</body>
</html>
