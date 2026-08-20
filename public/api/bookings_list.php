<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

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
    'SELECT id, title, location, notes, start_datetime, end_datetime, status
     FROM bookings
     WHERE status != "cancelled"
       AND start_datetime < :end
       AND end_datetime > :start
     ORDER BY start_datetime ASC'
);
$stmt->execute([
    'start' => $monday->format('Y-m-d H:i:s'),
    'end' => $nextMonday->format('Y-m-d H:i:s'),
]);
$bookings = $stmt->fetchAll();

if ($bookings) {
    $ids = array_column($bookings, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $attendeeStmt = $pdo->prepare(
        "SELECT bp.booking_id, p.id, p.name, p.email
         FROM booking_people bp
         JOIN people p ON p.id = bp.person_id
         WHERE bp.booking_id IN ($placeholders)
         ORDER BY p.name ASC"
    );
    $attendeeStmt->execute($ids);
    $attendeesByBooking = [];
    foreach ($attendeeStmt->fetchAll() as $row) {
        $attendeesByBooking[$row['booking_id']][] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
        ];
    }
    foreach ($bookings as &$booking) {
        $booking['id'] = (int) $booking['id'];
        $booking['attendees'] = $attendeesByBooking[$booking['id']] ?? [];
    }
    unset($booking);
}

json_ok([
    'week_start' => $monday->format('Y-m-d'),
    'bookings' => $bookings,
]);
