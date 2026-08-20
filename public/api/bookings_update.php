<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/google_calendar.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$body = json_body();
$pdo = db();

$existing = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
$existing->execute([$id]);
$booking = $existing->fetch();
if (!$booking) {
    json_error('Booking not found', 404);
}
if ($booking['status'] === 'cancelled') {
    json_error('Cannot edit a cancelled booking');
}

$title = trim((string) ($body['title'] ?? $booking['title']));
$location = trim((string) ($body['location'] ?? ($booking['location'] ?? '')));
$notes = trim((string) ($body['notes'] ?? ($booking['notes'] ?? '')));
$start = (string) ($body['start_datetime'] ?? $booking['start_datetime']);
$end = (string) ($body['end_datetime'] ?? $booking['end_datetime']);
$attendeeIds = isset($body['attendee_ids'])
    ? array_values(array_unique(array_map('intval', $body['attendee_ids'])))
    : null;

if ($title === '') {
    json_error('Title is required');
}

$startDt = DateTime::createFromFormat('Y-m-d H:i:s', $start) ?: DateTime::createFromFormat('Y-m-d\TH:i', $start);
$endDt = DateTime::createFromFormat('Y-m-d H:i:s', $end) ?: DateTime::createFromFormat('Y-m-d\TH:i', $end);
if (!$startDt || !$endDt) {
    json_error('Invalid start_datetime or end_datetime');
}
if ($endDt <= $startDt) {
    json_error('end_datetime must be after start_datetime');
}

if ($attendeeIds !== null) {
    if (empty($attendeeIds)) {
        json_error('At least one attendee is required');
    }
    $placeholders = implode(',', array_fill(0, count($attendeeIds), '?'));
    $check = $pdo->prepare("SELECT id FROM people WHERE id IN ($placeholders) AND active = 1");
    $check->execute($attendeeIds);
    $validIds = array_map('intval', array_column($check->fetchAll(), 'id'));
    if (count($validIds) !== count($attendeeIds)) {
        json_error('One or more attendees are invalid or inactive');
    }
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'UPDATE bookings SET title = :title, location = :location, notes = :notes,
         start_datetime = :start, end_datetime = :end WHERE id = :id'
    );
    $stmt->execute([
        'title' => $title,
        'location' => $location ?: null,
        'notes' => $notes ?: null,
        'start' => $startDt->format('Y-m-d H:i:s'),
        'end' => $endDt->format('Y-m-d H:i:s'),
        'id' => $id,
    ]);

    if ($attendeeIds !== null) {
        $pdo->prepare('DELETE FROM booking_people WHERE booking_id = ?')->execute([$id]);
        $attachStmt = $pdo->prepare('INSERT INTO booking_people (booking_id, person_id) VALUES (?, ?)');
        foreach ($validIds as $personId) {
            $attachStmt->execute([$id, $personId]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error('Failed to update booking', 500);
}

$syncResults = [];
if ($booking['status'] === 'confirmed') {
    $finalAttendeeStmt = $pdo->prepare(
        'SELECT p.id, p.name, p.email
         FROM booking_people bp JOIN people p ON p.id = bp.person_id
         WHERE bp.booking_id = ?'
    );
    $finalAttendeeStmt->execute([$id]);
    $finalAttendees = $finalAttendeeStmt->fetchAll();
    $finalIds = array_map(fn($a) => (int) $a['id'], $finalAttendees);

    $syncedStmt = $pdo->prepare(
        'SELECT bce.person_id, bce.google_event_id, p.email, p.name
         FROM booking_calendar_events bce JOIN people p ON p.id = bce.person_id
         WHERE bce.booking_id = ? AND bce.last_sync_status = "ok"'
    );
    $syncedStmt->execute([$id]);
    $synced = $syncedStmt->fetchAll();

    $calendar = new GoogleCalendarService();
    $freshBooking = array_merge($booking, [
        'title' => $title,
        'location' => $location ?: null,
        'notes' => $notes ?: null,
        'start_datetime' => $startDt->format('Y-m-d H:i:s'),
        'end_datetime' => $endDt->format('Y-m-d H:i:s'),
    ]);

    // Removed attendees: delete their calendar event.
    foreach ($synced as $s) {
        if (in_array((int) $s['person_id'], $finalIds, true)) {
            continue;
        }
        try {
            $calendar->deleteEvent($s['email'], $s['google_event_id']);
            $pdo->prepare('DELETE FROM booking_calendar_events WHERE booking_id = ? AND person_id = ?')
                ->execute([$id, $s['person_id']]);
            $syncResults[] = ['person' => $s['name'], 'status' => 'removed'];
        } catch (Throwable $e) {
            $syncResults[] = ['person' => $s['name'], 'status' => 'error', 'error' => $e->getMessage()];
        }
    }

    $syncedByPerson = [];
    foreach ($synced as $s) {
        $syncedByPerson[(int) $s['person_id']] = $s;
    }

    $upsertEvent = $pdo->prepare(
        'INSERT INTO booking_calendar_events (booking_id, person_id, google_calendar_id, google_event_id, last_sync_status)
         VALUES (:booking_id, :person_id, "primary", :event_id, "ok")
         ON DUPLICATE KEY UPDATE google_event_id = VALUES(google_event_id), last_sync_status = "ok",
           last_sync_error = NULL, synced_at = CURRENT_TIMESTAMP'
    );

    foreach ($finalAttendees as $attendee) {
        $personId = (int) $attendee['id'];
        $others = array_values(array_filter($finalAttendees, fn($a) => (int) $a['id'] !== $personId));
        $bookingData = array_merge($freshBooking, ['other_attendees' => $others]);

        try {
            if (isset($syncedByPerson[$personId])) {
                $calendar->updateEvent($attendee['email'], $syncedByPerson[$personId]['google_event_id'], $bookingData);
                $syncResults[] = ['person' => $attendee['name'], 'status' => 'updated'];
            } else {
                $eventId = $calendar->createEvent($attendee['email'], $bookingData);
                $upsertEvent->execute(['booking_id' => $id, 'person_id' => $personId, 'event_id' => $eventId]);
                $syncResults[] = ['person' => $attendee['name'], 'status' => 'ok'];
            }
        } catch (Throwable $e) {
            $syncResults[] = ['person' => $attendee['name'], 'status' => 'error', 'error' => $e->getMessage()];
        }
    }
}

json_ok(['id' => $id, 'sync_results' => $syncResults]);
