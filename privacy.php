<?php require 'header.php'; ?>
<section class="container">
  <div class="card" style="padding:28px">
    <h1 class="h2">Privacy Policy</h1>
    <p class="muted">Last updated: <?= date('F j, Y') ?></p>

    <p>At <?= htmlspecialchars(HOTEL_NAME) ?>, your privacy is important to us. This Privacy Policy explains how we collect, use, and safeguard your information when you stay with us, use our website, or make a booking.</p>

    <h2 class="h3">1. Information We Collect</h2>
    <ul>
      <li><strong>Personal information:</strong> name, email address, phone number, billing details, and identification provided during booking or check-in.</li>
      <li><strong>Booking details:</strong> room type, dates of stay, special requests, and payment records.</li>
      <li><strong>Website usage data:</strong> basic analytics such as pages visited and time spent on our site (collected anonymously).</li>
    </ul>

    <h2 class="h3">2. How We Use Your Information</h2>
    <ul>
      <li>To process reservations and payments.</li>
      <li>To communicate booking confirmations, changes, and updates.</li>
      <li>To improve our services, dining, and guest experiences.</li>
      <li>To comply with legal and regulatory requirements.</li>
    </ul>

    <h2 class="h3">3. Sharing of Information</h2>
    <p>We do not sell or rent your personal information. We may share data only with:</p>
    <ul>
      <li>Payment providers (e.g., Stripe) to securely process transactions.</li>
      <li>Service partners who help us manage reservations, subject to confidentiality agreements.</li>
      <li>Authorities when required by law or to ensure guest safety.</li>
    </ul>

    <h2 class="h3">4. Data Security</h2>
    <p>We implement reasonable safeguards (SSL encryption, secure databases, limited staff access) to protect your data. However, no system is completely secure, and we cannot guarantee 100% protection.</p>

    <h2 class="h3">5. Cookies</h2>
    <p>Our website may use cookies to improve usability. You can disable cookies in your browser settings, but some features may not function properly.</p>

    <h2 class="h3">6. Your Rights</h2>
    <p>You may request to access, update, or delete your personal information by contacting us at <a href="mailto:reservations@example.com">reservations@example.com</a>.</p>

    <h2 class="h3">7. Contact Us</h2>
    <p>If you have questions about this Privacy Policy, please contact us at:</p>
    <p>
      <?= htmlspecialchars(HOTEL_NAME) ?><br>
      353 Main Ave, Norwalk, CT 06851<br>
      reservations@example.com<br>
      (203) 555-0123
    </p>
  </div>
</section>
<?php require 'footer.php'; ?>
