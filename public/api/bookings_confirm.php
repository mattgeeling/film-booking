<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/google_calendar.php';

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

$attendeeStmt = $pdo->prepare(
    'SELECT p.id, p.name, p.email
     FROM booking_people bp
     JOIN people p ON p.id = bp.person_id
     WHERE bp.booking_id = ?'
);
$attendeeStmt->execute([$id]);
$attendees = $attendeeStmt->fetchAll();

$syncedStmt = $pdo->prepare(
    'SELECT person_id, google_event_id FROM booking_calendar_events
     WHERE booking_id = ? AND last_sync_status = "ok"'
);
$syncedStmt->execute([$id]);
$alreadySynced = [];
foreach ($syncedStmt->fetchAll() as $row) {
    $alreadySynced[(int) $row['person_id']] = $row['google_event_id'];
}

$calendar = new GoogleCalendarService();
$insertEvent = $pdo->prepare(
    'INSERT INTO booking_calendar_events (booking_id, person_id, google_calendar_id, google_event_id, last_sync_status)
     VALUES (:booking_id, :person_id, "primary", :event_id, "ok")
     ON DUPLICATE KEY UPDATE google_event_id = VALUES(google_event_id), last_sync_status = "ok",
       last_sync_error = NULL, synced_at = CURRENT_TIMESTAMP'
);

$results = [];
foreach ($attendees as $attendee) {
    $personId = (int) $attendee['id'];

    if (isset($alreadySynced[$personId])) {
        $results[] = ['person' => $attendee['name'], 'status' => 'already_synced'];
        continue;
    }

    $others = array_values(array_filter($attendees, fn($a) => (int) $a['id'] !== $personId));
    $bookingData = $booking;
    $bookingData['other_attendees'] = $others;

    try {
        $eventId = $calendar->createEvent($attendee['email'], $bookingData);
        $insertEvent->execute(['booking_id' => $id, 'person_id' => $personId, 'event_id' => $eventId]);
        $results[] = ['person' => $attendee['name'], 'status' => 'ok'];
    } catch (Throwable $e) {
        $results[] = ['person' => $attendee['name'], 'status' => 'error', 'error' => $e->getMessage()];
    }
}

if ($booking['status'] === 'pencil') {
    $pdo->prepare('UPDATE bookings SET status = "confirmed", confirmed_at = NOW() WHERE id = ?')->execute([$id]);
}

json_ok(['id' => $id, 'results' => $results]);
