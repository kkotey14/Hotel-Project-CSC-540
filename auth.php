<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function current_user() { return $_SESSION['user'] ?? null; }
function is_logged_in() { return !!current_user(); }
function require_login() {
  if (!is_logged_in()) { header("Location: login.php"); exit; }
}
function require_role($roles = []) {
  require_login();
  $u = current_user();
  if (!in_array($u['role'], $roles)) { header("Location: index.php"); exit; }
}
