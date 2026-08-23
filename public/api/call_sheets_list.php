<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$excludeBookingId = (int) ($_GET['exclude_booking_id'] ?? 0);

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT cs.booking_id, b.title, b.start_datetime
     FROM call_sheets cs
     JOIN bookings b ON b.id = cs.booking_id
     WHERE cs.booking_id != ?
     ORDER BY b.start_datetime DESC
     LIMIT 30'
);
$stmt->execute([$excludeBookingId]);

json_ok(['call_sheets' => $stmt->fetchAll()]);
