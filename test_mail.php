<?php
require __DIR__ . '/lib_mail.php';

$html = '<h2>Test Email</h2><p>This is a Mailtrap test 🎉</p>';

if (send_mail('demo@example.com', 'Mailtrap Test', $html)) {
    echo "✅ Email sent! Check your Mailtrap inbox.";
} else {
    echo "❌ Failed (check php_error_log).";
}