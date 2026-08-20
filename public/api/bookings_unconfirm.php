<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/booking_sync.php';

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
    json_error('Only confirmed bookings can be reverted to pencil');
}

$pdo->prepare('UPDATE bookings SET status = "pencil", confirmed_at = NULL WHERE id = ?')->execute([$id]);
$booking['status'] = 'pencil';

// Re-sync so the calendar event flips back from confirmed to tentative.
$results = sync_booking_calendar($pdo, $booking);

json_ok(['id' => $id, 'results' => $results]);
