<?php
// bookings_new.php
// Legacy entry point when user clicked "Book".
// New behavior: DO NOT create a booking here. Just forward to checkout with the right params.

require 'auth.php';
require_login();

// Accept params from GET or POST, under either new or old names.
$room_id = (int)($_REQUEST['room_id'] ?? 0);
$ci      = $_REQUEST['ci'] ?? $_REQUEST['check_in']  ?? '';
$co      = $_REQUEST['co'] ?? $_REQUEST['check_out'] ?? '';
$guests  = max(1, (int)($_REQUEST['guests'] ?? 1));

// If room_id missing, just go back to rooms list.
if (!$room_id) { header("Location: rooms_list.php"); exit; }

// Compose new-style query and redirect to checkout.
$q = http_build_query([
  'room_id' => $room_id,
  'ci'      => $ci,
  'co'      => $co,
  'guests'  => $guests,
]);

header("Location: checkout.php?$q");
exit;