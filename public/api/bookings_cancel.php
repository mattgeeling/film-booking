<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/booking_sync.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
$stmt->execute([$id]);
$booking = $stmt->fetch();
if (!$booking) {
    json_error('Booking not found', 404);
}

if ($booking['status'] === 'pencil') {
    // Pencil bookings are synced to Calendar as tentative from the moment
    // they're created, so their event(s) still need cleaning up.
    delete_booking_calendar_events($pdo, $id);
    $pdo->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
    json_ok(['id' => $id, 'deleted' => true]);
}

if ($booking['status'] === 'confirmed') {
    delete_booking_calendar_events($pdo, $id);
    $pdo->prepare('UPDATE bookings SET status = "cancelled", cancelled_at = NOW() WHERE id = ?')
        ->execute([$id]);
    json_ok(['id' => $id, 'deleted' => false]);
}

json_error('Booking is already cancelled');
