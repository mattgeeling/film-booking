<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$body = json_body();
$start = (string) ($body['start_datetime'] ?? '');
$end = (string) ($body['end_datetime'] ?? '');
$attendeeIds = array_values(array_unique(array_map('intval', $body['attendee_ids'] ?? [])));
$excludeBookingId = !empty($body['exclude_booking_id']) ? (int) $body['exclude_booking_id'] : null;

$startDt = DateTime::createFromFormat('Y-m-d H:i:s', $start) ?: DateTime::createFromFormat('Y-m-d\TH:i', $start);
$endDt = DateTime::createFromFormat('Y-m-d H:i:s', $end) ?: DateTime::createFromFormat('Y-m-d\TH:i', $end);

if (!$startDt || !$endDt || empty($attendeeIds)) {
    json_ok(['conflicts' => []]);
}

$pdo = db();
$placeholders = implode(',', array_fill(0, count($attendeeIds), '?'));
$sql = "SELECT b.id, b.title, b.start_datetime, b.end_datetime, p.id AS person_id, p.name AS person_name
        FROM bookings b
        JOIN booking_people bp ON bp.booking_id = b.id
        JOIN people p ON p.id = bp.person_id
        WHERE b.status != 'cancelled'
          AND bp.person_id IN ($placeholders)
          AND b.start_datetime < ?
          AND b.end_datetime > ?";
$params = $attendeeIds;
$params[] = $endDt->format('Y-m-d H:i:s');
$params[] = $startDt->format('Y-m-d H:i:s');

if ($excludeBookingId) {
    $sql .= ' AND b.id != ?';
    $params[] = $excludeBookingId;
}
$sql .= ' ORDER BY b.start_datetime ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$conflicts = [];
foreach ($stmt->fetchAll() as $row) {
    $conflicts[] = [
        'booking_id' => (int) $row['id'],
        'booking_title' => $row['title'],
        'start_datetime' => $row['start_datetime'],
        'end_datetime' => $row['end_datetime'],
        'person_id' => (int) $row['person_id'],
        'person_name' => $row['person_name'],
    ];
}

json_ok(['conflicts' => $conflicts]);
