<?php

require_once __DIR__ . '/google_calendar.php';

/**
 * Creates/patches/removes Google Calendar events for a booking's current
 * attendees so the calendar matches the booking's current attendee list,
 * details, and status (pencil -> tentative event, confirmed -> confirmed
 * event). Used on create, edit, and confirm. Returns per-attendee results.
 */
function sync_booking_calendar(PDO $pdo, array $booking): array
{
    if (!empty($booking['skip_calendar_sync'])) {
        return [];
    }

    $id = (int) $booking['id'];

    $attendeeStmt = $pdo->prepare(
        'SELECT p.id, p.name, p.email
         FROM booking_people bp JOIN people p ON p.id = bp.person_id
         WHERE bp.booking_id = ?'
    );
    $attendeeStmt->execute([$id]);
    $attendees = $attendeeStmt->fetchAll();
    $attendeeIds = array_map(fn($a) => (int) $a['id'], $attendees);

    $syncedStmt = $pdo->prepare(
        'SELECT bce.person_id, bce.google_event_id, p.email, p.name
         FROM booking_calendar_events bce JOIN people p ON p.id = bce.person_id
         WHERE bce.booking_id = ? AND bce.last_sync_status = "ok"'
    );
    $syncedStmt->execute([$id]);
    $synced = $syncedStmt->fetchAll();
    $syncedByPerson = [];
    foreach ($synced as $s) {
        $syncedByPerson[(int) $s['person_id']] = $s;
    }

    $calendar = new GoogleCalendarService();
    $results = [];

    // Attendees who were removed: delete their event.
    foreach ($synced as $s) {
        if (in_array((int) $s['person_id'], $attendeeIds, true)) {
            continue;
        }
        try {
            $calendar->deleteEvent($s['email'], $s['google_event_id']);
            $pdo->prepare('DELETE FROM booking_calendar_events WHERE booking_id = ? AND person_id = ?')
                ->execute([$id, $s['person_id']]);
            $results[] = ['person' => $s['name'], 'status' => 'removed'];
        } catch (Throwable $e) {
            $results[] = ['person' => $s['name'], 'status' => 'error', 'error' => $e->getMessage()];
        }
    }

    $upsertEvent = $pdo->prepare(
        'INSERT INTO booking_calendar_events (booking_id, person_id, google_calendar_id, google_event_id, last_sync_status)
         VALUES (:booking_id, :person_id, "primary", :event_id, "ok")
         ON DUPLICATE KEY UPDATE google_event_id = VALUES(google_event_id), last_sync_status = "ok",
           last_sync_error = NULL, synced_at = CURRENT_TIMESTAMP'
    );

    foreach ($attendees as $attendee) {
        $personId = (int) $attendee['id'];
        $others = array_values(array_filter($attendees, fn($a) => (int) $a['id'] !== $personId));
        $bookingData = array_merge($booking, ['other_attendees' => $others]);

        try {
            if (isset($syncedByPerson[$personId])) {
                $calendar->updateEvent($attendee['email'], $syncedByPerson[$personId]['google_event_id'], $bookingData);
                $results[] = ['person' => $attendee['name'], 'status' => 'updated'];
            } else {
                $eventId = $calendar->createEvent($attendee['email'], $bookingData);
                $upsertEvent->execute(['booking_id' => $id, 'person_id' => $personId, 'event_id' => $eventId]);
                $results[] = ['person' => $attendee['name'], 'status' => 'ok'];
            }
        } catch (Throwable $e) {
            $results[] = ['person' => $attendee['name'], 'status' => 'error', 'error' => $e->getMessage()];
        }
    }

    return $results;
}

/**
 * Best-effort deletes every synced Google Calendar event for a booking
 * (used when a booking is cancelled/removed).
 */
function delete_booking_calendar_events(PDO $pdo, int $bookingId): void
{
    $syncedStmt = $pdo->prepare(
        'SELECT bce.person_id, bce.google_event_id, p.email
         FROM booking_calendar_events bce JOIN people p ON p.id = bce.person_id
         WHERE bce.booking_id = ? AND bce.last_sync_status = "ok"'
    );
    $syncedStmt->execute([$bookingId]);

    $calendar = new GoogleCalendarService();
    foreach ($syncedStmt->fetchAll() as $s) {
        try {
            $calendar->deleteEvent($s['email'], $s['google_event_id']);
        } catch (Throwable $e) {
            // Best-effort: still remove the local record even if the delete fails.
        }
    }
    $pdo->prepare('DELETE FROM booking_calendar_events WHERE booking_id = ?')->execute([$bookingId]);
}
