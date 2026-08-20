<?php

/**
 * Attaches attendees and normalizes types on a set of booking rows fetched
 * via the shared SELECT column list (see bookings_list.php / bookings_month_list.php).
 */
function hydrate_bookings(PDO $pdo, array $bookings): array
{
    if (!$bookings) {
        return $bookings;
    }

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

    return $bookings;
}

const BOOKING_SELECT_COLUMNS = 'b.id, b.title, b.location, b.what3words, b.notes, b.start_datetime, b.end_datetime, b.status,
    b.checklist_call_sheet, b.checklist_call_sheet_by, b.checklist_call_sheet_url,
    b.checklist_risk_assessment, b.checklist_risk_assessment_by, b.checklist_risk_assessment_url,
    b.checklist_shot_list, b.checklist_shot_list_by, b.checklist_shot_list_url,
    b.checklist_preproduction_creative, b.checklist_preproduction_creative_by, b.checklist_preproduction_creative_url,
    b.skip_calendar_sync, b.kit_source, b.created_by_name, b.client_id, c.name AS client_name, c.logo_path AS client_logo_path';
