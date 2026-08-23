<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/call_sheet_mailer.php';

require_login();

$bookingId = (int) ($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    json_error('Missing or invalid booking_id');
}

try {
    $preview = preview_call_sheet_email(db(), $bookingId);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 404);
}

json_ok($preview);
