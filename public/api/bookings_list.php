<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/booking_query.php';

require_login();

$weekStartParam = $_GET['week_start'] ?? date('Y-m-d');
$requested = DateTime::createFromFormat('Y-m-d', $weekStartParam);
if (!$requested) {
    json_error('Invalid week_start, expected YYYY-MM-DD');
}

// Normalize to the Monday of that week.
$dayOfWeek = (int) $requested->format('N'); // 1 (Mon) .. 7 (Sun)
$monday = (clone $requested)->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
$nextMonday = (clone $monday)->modify('+7 days');

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT ' . BOOKING_SELECT_COLUMNS . '
     FROM bookings b
     LEFT JOIN clients c ON c.id = b.client_id
     WHERE b.status != "cancelled"
       AND b.start_datetime < :end
       AND b.end_datetime > :start
     ORDER BY b.start_datetime ASC'
);
$stmt->execute([
    'start' => $monday->format('Y-m-d H:i:s'),
    'end' => $nextMonday->format('Y-m-d H:i:s'),
]);
$bookings = hydrate_bookings($pdo, $stmt->fetchAll());

json_ok([
    'week_start' => $monday->format('Y-m-d'),
    'bookings' => $bookings,
]);
