<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/google_calendar.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
$stmt->execute([$id]);
$booking = $stmt->fetch();
if (!$booking) {
    json_error('Booking not found', 404);
}

if ($booking['status'] === 'pencil') {
    // Nothing external synced yet — safe to hard-delete.
    $pdo->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
    json_ok(['id' => $id, 'deleted' => true]);
}

if ($booking['status'] === 'confirmed') {
    $syncedStmt = $pdo->prepare(
        'SELECT bce.person_id, bce.google_event_id, p.email
         FROM booking_calendar_events bce JOIN people p ON p.id = bce.person_id
         WHERE bce.booking_id = ? AND bce.last_sync_status = "ok"'
    );
    $syncedStmt->execute([$id]);

    $calendar = new GoogleCalendarService();
    foreach ($syncedStmt->fetchAll() as $s) {
        try {
            $calendar->deleteEvent($s['email'], $s['google_event_id']);
        } catch (Throwable $e) {
            // Best-effort: still soft-cancel the booking even if a delete fails.
        }
    }
    $pdo->prepare('DELETE FROM booking_calendar_events WHERE booking_id = ?')->execute([$id]);

    $pdo->prepare('UPDATE bookings SET status = "cancelled", cancelled_at = NOW() WHERE id = ?')
        ->execute([$id]);
    json_ok(['id' => $id, 'deleted' => false]);
}

json_error('Booking is already cancelled');
