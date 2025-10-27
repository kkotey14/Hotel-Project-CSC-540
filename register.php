<?php
require 'db.php';
require 'header.php';

/**
 * Helper: check if a column exists (so we can insert address/dob only when present)
 */
function column_exists(PDO $pdo, string $table, string $column): bool {
  static $cache = [];
  $key = $table . '.' . $column;
  if (isset($cache[$key])) return $cache[$key];
  $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
  $stmt->execute([$column]);
  $cache[$key] = (bool)$stmt->fetch();
  return $cache[$key];
}

$err = $ok = '';
$values = [
  'first_name' => '',
  'last_name'  => '',
  'email'      => '',
  'address'    => '',
  'dob'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Collect & trim
  foreach ($values as $k => $_) {
    $values[$k] = trim($_POST[$k] ?? '');
  }
  $password = $_POST['password'] ?? '';

  // Basic validation
  if ($values['first_name'] === '' || $values['last_name'] === '' || $values['email'] === '' || $password === '') {
    $err = 'First name, last name, email and password are required.';
  } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
    $err = 'Please enter a valid email address.';
  } elseif (strlen($password) < 8) {
    $err = 'Password must be at least 8 characters.';
  } else {
    // Validate DOB if provided (expecting HTML <input type="date"> -> Y-m-d)
    $dobStore = null;
    if ($values['dob'] !== '') {
      $dt = DateTime::createFromFormat('Y-m-d', $values['dob']);
      if (!$dt) {
        $err = 'Date of birth must be a valid date.';
      } else {
        $dobStore = $dt->format('Y-m-d');
      }
    }
  }

  if ($err === '') {
    // Unique email?
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$values['email']]);
    if ($stmt->fetch()) {
      $err = 'That email is already registered. Try signing in.';
    } else {
      // Build name and hash
      $name = trim($values['first_name'] . ' ' . $values['last_name']);
      $hash = password_hash($password, PASSWORD_DEFAULT);

      // Prepare dynamic insert (address/dob optional)
      $cols = ['name','email','password_hash','role'];
      $params = [$name, $values['email'], $hash, 'customer'];

      if (column_exists($pdo, 'users', 'address')) {
        $cols[] = 'address';
        $params[] = $values['address'];
      }
      if (column_exists($pdo, 'users', 'dob')) {
        $cols[] = 'dob';
        $params[] = $dobStore;
      }

      $placeholders = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
      $sql = 'INSERT INTO users (' . implode(',', $cols) . ') VALUES ' . $placeholders;
      $ins = $pdo->prepare($sql);
      $ins->execute($params);

      $newId = $pdo->lastInsertId();
      $ok = "Account created successfully. Your user ID is #{$newId}. You can now log in.";
      // Clear form values after success
      $values = ['first_name'=>'','last_name'=>'','email'=>'','address'=>'','dob'=>''];
    }
  }
}
?>

<section class="container" style="padding-top:28px">
  <div class="card" style="max-width:860px;margin:0 auto;">
    <h2 class="h2" style="margin-bottom:10px">Create an Account</h2>
    <p class="muted" style="margin-top:0">Join The Riverside to book faster and manage your stays.</p>

    <?php if($err): ?>
      <div class="flash error" style="margin:12px 0"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <?php if($ok): ?>
      <div class="flash" style="margin:12px 0"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on" style="margin-top:10px">
      <div class="grid" style="margin-bottom:10px">
        <div class="span-6">
          <label class="tiny muted">First name</label>
          <input class="input" name="first_name" value="<?= htmlspecialchars($values['first_name']) ?>" required>
        </div>
        <div class="span-6">
          <label class="tiny muted">Last name</label>
          <input class="input" name="last_name" value="<?= htmlspecialchars($values['last_name']) ?>" required>
        </div>
      </div>

      <div class="grid" style="margin-bottom:10px">
        <div class="span-6">
          <label class="tiny muted">Email</label>
          <input class="input" type="email" name="email" value="<?= htmlspecialchars($values['email']) ?>" required>
        </div>
        <div class="span-6">
          <label class="tiny muted">Password</label>
          <input class="input" type="password" name="password" minlength="8" placeholder="At least 8 characters" required>
        </div>
      </div>

      <div class="grid" style="margin-bottom:10px">
        <div class="span-8">
          <label class="tiny muted">Address <span class="muted">(optional)</span></label>
          <input class="input" name="address" value="<?= htmlspecialchars($values['address']) ?>" placeholder="Street, City, State, ZIP">
        </div>
        <div class="span-4">
          <label class="tiny muted">Date of birth <span class="muted">(optional)</span></label>
          <input class="input" type="date" name="dob" value="<?= htmlspecialchars($values['dob']) ?>">
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
        <button class="btn primary" type="submit">Register</button>
        <span class="muted tiny">Already have an account? <a href="login.php">Sign in</a></span>
      </div>
    </form>
  </div>
</section>

<?php require 'footer.php'; ?>