<?php

/**
 * Film or install. Kept in bookings.doc_type, which is otherwise empty on a
 * calendar booking: NULL = film, 'install' = install. A standalone document
 * keeps its own value there; 'install_ra' is an install risk assessment.
 */
function booking_type_of(array $booking): string
{
    return in_array($booking['doc_type'] ?? null, ['install', 'install_ra'], true) ? 'install' : 'film';
}

/**
 * A call sheet that isn't needed: ticked off, with this in place of who did
 * it, so it counts as done everywhere a booking's paperwork is checked.
 */
const CALL_SHEET_NOT_REQUIRED = 'Not required';

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
        $booking['booking_type'] = booking_type_of($booking);
        $booking['checklist_call_sheet_na'] = ($booking['checklist_call_sheet_by'] ?? null) === CALL_SHEET_NOT_REQUIRED;
        $booking['attendees'] = $attendeesByBooking[$booking['id']] ?? [];
        $booking['checklist_call_sheet'] = (bool) $booking['checklist_call_sheet'];
        $booking['checklist_risk_assessment'] = (bool) $booking['checklist_risk_assessment'];
        $booking['checklist_shot_list'] = (bool) $booking['checklist_shot_list'];
        $booking['checklist_shot_list_na'] = (bool) $booking['checklist_shot_list_na'];
        $booking['checklist_preproduction_creative'] = (bool) $booking['checklist_preproduction_creative'];
        $booking['checklist_additional_documents'] = (bool) $booking['checklist_additional_documents'];
        $booking['skip_calendar_sync'] = (bool) $booking['skip_calendar_sync'];
        $booking['client_id'] = $booking['client_id'] !== null ? (int) $booking['client_id'] : null;
    }
    unset($booking);

    return $bookings;
}

const BOOKING_SELECT_COLUMNS = 'b.id, b.title, b.location, b.what3words, b.shoot_prep_folder_url, b.notes, b.start_datetime, b.end_datetime, b.status,
    b.checklist_call_sheet, b.checklist_call_sheet_by, b.checklist_call_sheet_url,
    b.checklist_risk_assessment, b.checklist_risk_assessment_by, b.checklist_risk_assessment_url,
    b.checklist_shot_list, b.checklist_shot_list_by, b.checklist_shot_list_url, b.checklist_shot_list_na,
    b.checklist_preproduction_creative, b.checklist_preproduction_creative_by, b.checklist_preproduction_creative_url,
    b.checklist_additional_documents, b.checklist_additional_documents_by, b.checklist_additional_documents_url,
    b.skip_calendar_sync, b.kit_source, b.created_by_name, b.client_id, c.name AS client_name, c.logo_path AS client_logo_path,
    b.synergist_job_number, b.doc_type';
