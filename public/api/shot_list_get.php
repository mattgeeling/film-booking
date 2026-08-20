<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$bookingId = (int) ($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    json_error('Missing or invalid booking_id');
}

$pdo = db();

$bookingStmt = $pdo->prepare(
    'SELECT b.*, c.name AS client_name
     FROM bookings b LEFT JOIN clients c ON c.id = b.client_id
     WHERE b.id = ?'
);
$bookingStmt->execute([$bookingId]);
$booking = $bookingStmt->fetch();
if (!$booking) {
    json_error('Booking not found', 404);
}

$listStmt = $pdo->prepare('SELECT * FROM shot_lists WHERE booking_id = ?');
$listStmt->execute([$bookingId]);
$shotList = $listStmt->fetch();

$subtitle = $shotList['subtitle'] ?? ('Shot List - ' . $booking['title']);
$sections = ($shotList && $shotList['sections']) ? json_decode($shotList['sections'], true) : [];

json_ok([
    'booking' => [
        'id' => (int) $booking['id'],
        'title' => $booking['title'],
        'client_name' => $booking['client_name'],
        'start_datetime' => $booking['start_datetime'],
    ],
    'subtitle' => $subtitle,
    'sections' => $sections,
    'saved' => (bool) $shotList,
]);
