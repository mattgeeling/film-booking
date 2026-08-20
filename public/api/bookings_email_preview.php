<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/mailer.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$pdo = db();

$bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
$bookingStmt->execute([$id]);
$booking = $bookingStmt->fetch();
if (!$booking) {
    json_error('Booking not found', 404);
}
if ($booking['status'] !== 'confirmed') {
    json_error('Only confirmed bookings can be emailed');
}

$preview = preview_confirmation_email($pdo, $booking);

json_ok($preview);
