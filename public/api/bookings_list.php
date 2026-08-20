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
    'SELECT b.id, b.title, b.location, b.what3words, b.notes, b.start_datetime, b.end_datetime, b.status,
            b.checklist_call_sheet, b.checklist_call_sheet_by, b.checklist_call_sheet_url,
            b.checklist_risk_assessment, b.checklist_risk_assessment_by, b.checklist_risk_assessment_url,
            b.checklist_shot_list, b.checklist_shot_list_by, b.checklist_shot_list_url,
            b.checklist_preproduction_creative, b.checklist_preproduction_creative_by, b.checklist_preproduction_creative_url,
            b.skip_calendar_sync, b.kit_source, b.created_by_name, b.client_id, c.name AS client_name, c.logo_path AS client_logo_path
     FROM bookings b
     LEFT JOIN clients c ON c.id = b.client_id
     WHERE b.status != "cancelled"
       AND b.start_datetime < :end
       AND b.end_datetime > :start
     ORDER BY b.start_datetime ASC'
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
        $booking['checklist_call_sheet'] = (bool) $booking['checklist_call_sheet'];
        $booking['checklist_risk_assessment'] = (bool) $booking['checklist_risk_assessment'];
        $booking['checklist_shot_list'] = (bool) $booking['checklist_shot_list'];
        $booking['checklist_preproduction_creative'] = (bool) $booking['checklist_preproduction_creative'];
        $booking['skip_calendar_sync'] = (bool) $booking['skip_calendar_sync'];
        $booking['client_id'] = $booking['client_id'] !== null ? (int) $booking['client_id'] : null;
    }
    unset($booking);
}

json_ok([
    'week_start' => $monday->format('Y-m-d'),
    'bookings' => $bookings,
]);
