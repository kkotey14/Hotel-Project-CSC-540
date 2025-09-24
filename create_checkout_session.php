<?php
require 'db.php';
require_once 'config.php';

header('Content-Type: application/json');

$booking_id = (int)($_GET['booking_id'] ?? 0);
if (!$booking_id) { echo json_encode(['error'=>'missing booking']); exit; }

$stmt = $pdo->prepare("SELECT b.id, r.type, r.number,
  (SELECT amount_cents FROM payments WHERE booking_id=b.id ORDER BY id DESC LIMIT 1) AS amount_cents
  FROM bookings b JOIN rooms r ON b.room_id=r.id
  WHERE b.id=?");
$stmt->execute([$booking_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo json_encode(['error'=>'not found']); exit; }

$base = (isset($_SERVER['HTTPS'])?'https':'http') . "://".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['REQUEST_URI']),'/');
$success_url = $base."/success.php?booking_id=".$booking_id;
$cancel_url  = $base."/cancel.php?booking_id=".$booking_id;

$payload = [
  'mode' => 'payment',
  'success_url' => $success_url,
  'cancel_url'  => $cancel_url,
  'payment_method_types[]' => 'card',
  'line_items[0][price_data][currency]' => 'usd',
  'line_items[0][price_data][product_data][name]' => $row['type']." — Room ".$row['number'],
  'line_items[0][price_data][unit_amount]' => (int)$row['amount_cents'],
  'line_items[0][quantity]' => 1,
  'metadata[booking_id]' => (string)$booking_id
];

$ch = curl_init("https://api.stripe.com/v1/checkout/sessions");
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => ["Authorization: Bearer ".STRIPE_SECRET],
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => http_build_query($payload),
  CURLOPT_RETURNTRANSFER => true,
]);
$out = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($code);
echo $out;
