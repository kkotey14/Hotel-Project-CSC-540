<?php
// Copy this to config.php (do not commit config.php)
// Fill with your own local values or use environment variables.

define('DB_HOST', 'localhost');
define('DB_NAME', 'hotelapp');
define('DB_USER', 'root');
define('DB_PASS', '');

// Stripe keys should come from env or be filled locally (never committed)
define('STRIPE_PUBLISHABLE', getenv('STRIPE_PUBLISHABLE') ?: 'pk_test_your_publishable_here');
define('STRIPE_SECRET',      getenv('STRIPE_SECRET') ?: 'sk_test_your_secret_here');

define('BASE_URL', '/hotelapp');

// Branding (optional)
if (!defined('HOTEL_NAME'))  define('HOTEL_NAME', 'The Riverside');
if (!defined('HOTEL_TAGLINE')) define('HOTEL_TAGLINE', 'Boutique stays by the river.');
$HOTEL_AMENITIES = [
  ['icon'=>'🛏️','title'=>'Plush Bedding','text'=>'Down duvets, premium linens'],
  ['icon'=>'📶','title'=>'Fast Wi-Fi','text'=>'Reliable, hotel-wide'],
  ['icon'=>'☕','title'=>'Coffee & Tea','text'=>'In-room Nespresso'],
];
