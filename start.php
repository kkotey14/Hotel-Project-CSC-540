<?php
/**
 * Hotel App bootstrap / installer
 * - Creates `hotelapp` DB if needed
 * - Creates core tables (idempotent)
 * - Seeds base data if tables are empty
 * - Optional reset via start.php?reset=1
 *
 * Place in project root and open:
 *   http://localhost/hotelapp/start.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* -------------------------------------------------
 * Load DB creds from config.php if available
 * ------------------------------------------------- */
$dbHost = 'localhost';
$dbName = 'hotelapp';
$dbUser = 'root';
$dbPass = '';

$projectRoot = __DIR__;
$configPath  = $projectRoot . '/config.php';
if (is_file($configPath)) {
  // config.php defines constants DB_HOST, DB_NAME, DB_USER, DB_PASS
  require_once $configPath;
  $dbHost = defined('DB_HOST') ? DB_HOST : $dbHost;
  $dbName = defined('DB_NAME') ? DB_NAME : $dbName;
  $dbUser = defined('DB_USER') ? DB_USER : $dbUser;
  $dbPass = defined('DB_PASS') ? DB_PASS : $dbPass;
}

$mysqli = new mysqli($dbHost, $dbUser, $dbPass);
if ($mysqli->connect_errno) {
  die("Connection failed: {$mysqli->connect_error}");
}

/* -------------------------------------------------
 * Create database if missing
 * ------------------------------------------------- */
$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "✔ Database ensured: <strong>{$dbName}</strong><br>";

$mysqli->select_db($dbName);

/* -------------------------------------------------
 * Optional reset: drop known tables (safe, explicit)
 * ------------------------------------------------- */
$reset = isset($_GET['reset']) && $_GET['reset'] === '1';
if ($reset) {
  $mysqli->query("SET foreign_key_checks = 0");
  $toDrop = [
    'bookings', 'room_photos', 'reviews', 'password_resets',
    'user_sessions', 'rooms', 'users', 'cleaning_schedule', 'payments'
  ];
  foreach ($toDrop as $t) {
    $mysqli->query("DROP TABLE IF EXISTS `{$t}`");
  }
  $mysqli->query("SET foreign_key_checks = 1");
  echo "⚠️ Reset mode: tables dropped.<br>";
}

/* -------------------------------------------------
 * Create tables (idempotent)
 * ------------------------------------------------- */
$sql = [];

/* users */
$sql[] = "
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name    VARCHAR(100) NOT NULL,
  last_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* rooms */
$sql[] = "
CREATE TABLE IF NOT EXISTS rooms (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type         VARCHAR(50)  NOT NULL,              -- Double, Queen, King, Suite
  number       INT          NOT NULL UNIQUE,       -- e.g. 101, 201
  floor        INT          NOT NULL DEFAULT 1,
  rate_cents   INT UNSIGNED NOT NULL DEFAULT 12999,
  max_guests   INT UNSIGNED NOT NULL DEFAULT 2,
  inventory    INT UNSIGNED NOT NULL DEFAULT 1,    -- number of identical units available
  image_url    VARCHAR(500) NULL,
  description  TEXT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* bookings */
$sql[] = "
CREATE TABLE IF NOT EXISTS bookings (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  room_id     INT UNSIGNED NOT NULL,
  unit_number INT UNSIGNED DEFAULT NULL,           -- which physical unit (1..inventory)
  guests      INT UNSIGNED NOT NULL DEFAULT 1,
  check_in    DATE NOT NULL,
  check_out   DATE NOT NULL,
  status      ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_user  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_room  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT chk_dates CHECK (check_out > check_in)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* reviews */
$sql[] = "
CREATE TABLE IF NOT EXISTS reviews (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  rating     TINYINT UNSIGNED NOT NULL,            -- 1..5
  comment    TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* room_photos */
$sql[] = "
CREATE TABLE IF NOT EXISTS room_photos (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id    INT UNSIGNED NOT NULL,
  url        VARCHAR(500) NOT NULL,
  photo_type ENUM('main','bathroom','other') NOT NULL DEFAULT 'other',
  caption    VARCHAR(255) NULL,
  CONSTRAINT fk_photos_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* password_resets */
$sql[] = "
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token      VARCHAR(190) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pw_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* user_sessions (optional audit) */
$sql[] = "
CREATE TABLE IF NOT EXISTS user_sessions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  session_id  VARCHAR(190) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at  DATETIME NULL,
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* cleaning_schedule (lightweight version) */
$sql[] = "
CREATE TABLE IF NOT EXISTS cleaning_schedule (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id       INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NULL,                 -- staff who cleaned
  last_cleaned  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_clean_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_clean_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* (optional) payments table stub kept minimal */
$sql[] = "
CREATE TABLE IF NOT EXISTS payments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  booking_id  INT UNSIGNED NOT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  status      ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_user    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* run all DDL */
foreach ($sql as $q) {
  if (!$mysqli->query($q)) {
    die("DDL error: {$mysqli->error}");
  }
}
echo "✔ Tables ensured.<br>";

/* -------------------------------------------------
 * Seed minimal data if empty
 * ------------------------------------------------- */
function table_is_empty(mysqli $m, string $t): bool {
  $res = $m->query("SELECT COUNT(*) c FROM `{$t}`");
  if (!$res) return true;
  $row = $res->fetch_assoc();
  return ((int)$row['c']) === 0;
}

/* Seed users (admin + sample customer) */
if (table_is_empty($mysqli, 'users')) {
  $stmt = $mysqli->prepare("INSERT INTO users (first_name,last_name,email,password_hash,role) VALUES (?,?,?,?,?)");
  $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
  $custPass  = password_hash('guest123', PASSWORD_DEFAULT);
  $role = 'admin';
  $stmt->bind_param('sssss', $fn,$ln,$email,$hash,$role);

  $fn='Riverside'; $ln='Admin'; $email='admin@example.com'; $hash=$adminPass; $role='admin';   $stmt->execute();
  $fn='Sample';    $ln='Guest'; $email='guest@example.com';  $hash=$custPass;  $role='customer';$stmt->execute();
  $stmt->close();
  echo "✔ Seeded users (admin/guest).<br>";
}

/* Seed rooms */
if (table_is_empty($mysqli, 'rooms')) {
  $stmt = $mysqli->prepare("
    INSERT INTO rooms (type, number, floor, rate_cents, max_guests, inventory, image_url, description, is_active)
    VALUES (?,?,?,?,?,?,?,?,1)
  ");
  $stmt->bind_param('siiiisss', $type,$number,$floor,$rate,$max,$inv,$img,$desc);

  // Double → 1st floor (room 101), inventory 10
  $type='Double'; $number=101; $floor=1; $rate=13999; $max=4; $inv=10;
  $img=''; $desc='Spacious Double room on the 1st floor.'; $stmt->execute();

  // Queen → 2nd floor (room 201), inventory 6
  $type='Queen';  $number=201; $floor=2; $rate=12999; $max=2; $inv=6;
  $img=''; $desc='Cozy Queen room on the 2nd floor.';   $stmt->execute();

  // King → 3rd floor (room 301), inventory 8
  $type='King';   $number=301; $floor=3; $rate=15999; $max=3; $inv=8;
  $img=''; $desc='Modern King room on the 3rd floor.';  $stmt->execute();

  // Suite → 4th floor (room 401), inventory 4
  $type='Suite';  $number=401; $floor=4; $rate=21999; $max=4; $inv=4;
  $img=''; $desc='Luxury Suite on the 4th floor.';      $stmt->execute();

  $stmt->close();
  echo "✔ Seeded rooms (Double/Queen/King/Suite).<br>";
}

/* -------------------------------------------------
 * Final info
 * ------------------------------------------------- */
echo "<hr>";
echo "✅ Done. Your environment is ready.<br>";
echo "• Admin login: <code>admin@example.com</code> / <code>admin123</code><br>";
echo "• Guest login: <code>guest@example.com</code> / <code>guest123</code><br>";
echo "• Go to <a href=\"/hotelapp/login.php\">/hotelapp/login.php</a> or <a href=\"/hotelapp/\">/hotelapp/</a><br>";
echo "• To RESET (drops & recreates tables): <a href=\"start.php?reset=1\">start.php?reset=1</a><br>";
?>