<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$userEmail = require_login();
$body = json_body();

$title = trim((string) ($body['title'] ?? ''));
$location = trim((string) ($body['location'] ?? ''));
$notes = trim((string) ($body['notes'] ?? ''));
$start = (string) ($body['start_datetime'] ?? '');
$end = (string) ($body['end_datetime'] ?? '');
$attendeeIds = array_values(array_unique(array_map('intval', $body['attendee_ids'] ?? [])));

if ($title === '') {
    json_error('Title is required');
}
if (empty($attendeeIds)) {
    json_error('At least one attendee is required');
}

$startDt = DateTime::createFromFormat('Y-m-d H:i:s', $start) ?: DateTime::createFromFormat('Y-m-d\TH:i', $start);
$endDt = DateTime::createFromFormat('Y-m-d H:i:s', $end) ?: DateTime::createFromFormat('Y-m-d\TH:i', $end);
if (!$startDt || !$endDt) {
    json_error('Invalid start_datetime or end_datetime');
}
if ($endDt <= $startDt) {
    json_error('end_datetime must be after start_datetime');
}

$pdo = db();

$placeholders = implode(',', array_fill(0, count($attendeeIds), '?'));
$check = $pdo->prepare("SELECT id FROM people WHERE id IN ($placeholders) AND active = 1");
$check->execute($attendeeIds);
$validIds = array_map('intval', array_column($check->fetchAll(), 'id'));
if (count($validIds) !== count($attendeeIds)) {
    json_error('One or more attendees are invalid or inactive');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO bookings (title, location, notes, start_datetime, end_datetime, status, created_by)
         VALUES (:title, :location, :notes, :start, :end, "pencil", :created_by)'
    );
    $stmt->execute([
        'title' => $title,
        'location' => $location ?: null,
        'notes' => $notes ?: null,
        'start' => $startDt->format('Y-m-d H:i:s'),
        'end' => $endDt->format('Y-m-d H:i:s'),
        'created_by' => $userEmail,
    ]);
    $bookingId = (int) $pdo->lastInsertId();

    $attachStmt = $pdo->prepare('INSERT INTO booking_people (booking_id, person_id) VALUES (?, ?)');
    foreach ($validIds as $personId) {
        $attachStmt->execute([$bookingId, $personId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error('Failed to create booking', 500);
}

json_ok(['id' => $bookingId], 201);
