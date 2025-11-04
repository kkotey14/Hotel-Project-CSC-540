<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib_mail.php';

$ok = send_mail('your_real_email@example.com', 'Test from HotelApp', '<h1>Mail works 🎉</h1>');
echo $ok ? 'Sent!' : 'Failed (check php_error_log)';