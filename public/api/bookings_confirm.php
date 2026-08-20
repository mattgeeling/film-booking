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
if ($booking['status'] === 'cancelled') {
    json_error('Cannot confirm a cancelled booking');
}

if ($booking['status'] === 'pencil') {
    $pdo->prepare('UPDATE bookings SET status = "confirmed", confirmed_at = NOW() WHERE id = ?')->execute([$id]);
    $booking['status'] = 'confirmed';
}

// Re-sync so already-tentative calendar events flip to confirmed (or get
// created now, if an earlier sync attempt had failed).
$results = sync_booking_calendar($pdo, $booking);

json_ok(['id' => $id, 'results' => $results]);
