<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$bookingId = (int) ($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    json_error('Missing or invalid booking_id');
}

$pdo = db();

$bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
$bookingStmt->execute([$bookingId]);
$booking = $bookingStmt->fetch();
if (!$booking) {
    json_error('Booking not found', 404);
}

$sheetStmt = $pdo->prepare('SELECT * FROM call_sheets WHERE booking_id = ?');
$sheetStmt->execute([$bookingId]);
$sheet = $sheetStmt->fetch();

$attendeeStmt = $pdo->prepare(
    'SELECT p.id, p.name, p.role, p.email
     FROM booking_people bp JOIN people p ON p.id = bp.person_id
     WHERE bp.booking_id = ?
     ORDER BY p.name ASC'
);
$attendeeStmt->execute([$bookingId]);
$attendees = $attendeeStmt->fetchAll();

$callTime = substr((string) $booking['start_datetime'], 11, 5);

if ($sheet) {
    $productionCrew = $sheet['production_crew'] ? json_decode($sheet['production_crew'], true) : null;
    $clientContacts = $sheet['client_contacts'] ? json_decode($sheet['client_contacts'], true) : [];
    $equipment = $sheet['equipment'] ? json_decode($sheet['equipment'], true) : [];
    $schedule = $sheet['schedule'] ? json_decode($sheet['schedule'], true) : [];
} else {
    $productionCrew = null;
    $clientContacts = [];
    $equipment = [];
    $schedule = [];
}

// First time opening the call sheet for this booking: prefill the crew
// list from the booking's actual attendees rather than starting blank.
if ($productionCrew === null) {
    $productionCrew = array_map(function ($a) use ($callTime) {
        return [
            'name' => $a['name'],
            'title' => $a['role'] ?? '',
            'contact' => '',
            'email' => $a['email'],
            'call_time' => $callTime,
        ];
    }, $attendees);
}

json_ok([
    'booking' => [
        'id' => (int) $booking['id'],
        'title' => $booking['title'],
        'location' => $booking['location'],
        'what3words' => $booking['what3words'],
        'start_datetime' => $booking['start_datetime'],
        'end_datetime' => $booking['end_datetime'],
    ],
    'day_info' => $sheet['day_info'] ?? '',
    'location_contact_name' => $sheet['location_contact_name'] ?? '',
    'location_contact_phone' => $sheet['location_contact_phone'] ?? '',
    'parking_notes' => $sheet['parking_notes'] ?? '',
    'weather_summary' => $sheet['weather_summary'] ?? '',
    'weather_icons' => !empty($sheet['weather_icons']) ? json_decode($sheet['weather_icons'], true) : null,
    'production_crew' => $productionCrew,
    'client_contacts' => $clientContacts,
    'equipment' => $equipment,
    'schedule' => $schedule,
    'nearest_ae' => $sheet['nearest_ae'] ?? '',
    'saved' => (bool) $sheet,
]);
