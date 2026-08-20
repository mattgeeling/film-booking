<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/booking_query.php';

require_login();

$monthParam = $_GET['month'] ?? date('Y-m');
$start = DateTime::createFromFormat('Y-m-d', $monthParam . '-01');
if (!$start) {
    json_error('Invalid month, expected YYYY-MM');
}
$start->setTime(0, 0, 0);
$end = (clone $start)->modify('+1 month');

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
    'start' => $start->format('Y-m-d H:i:s'),
    'end' => $end->format('Y-m-d H:i:s'),
]);
$bookings = hydrate_bookings($pdo, $stmt->fetchAll());

json_ok([
    'month' => $start->format('Y-m'),
    'bookings' => $bookings,
]);
