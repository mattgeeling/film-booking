<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/booking_sync.php';

$userEmail = require_login();

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
$what3words = trim((string) ($body['what3words'] ?? ($booking['what3words'] ?? '')));
$clientId = array_key_exists('client_id', $body)
    ? (!empty($body['client_id']) ? (int) $body['client_id'] : null)
    : $booking['client_id'];
$notes = trim((string) ($body['notes'] ?? ($booking['notes'] ?? '')));
$start = (string) ($body['start_datetime'] ?? $booking['start_datetime']);
$end = (string) ($body['end_datetime'] ?? $booking['end_datetime']);
$attendeeIds = isset($body['attendee_ids'])
    ? array_values(array_unique(array_map('intval', $body['attendee_ids'])))
    : null;
$checklistCallSheet = array_key_exists('checklist_call_sheet', $body)
    ? !empty($body['checklist_call_sheet']) : (bool) $booking['checklist_call_sheet'];
$checklistRiskAssessment = array_key_exists('checklist_risk_assessment', $body)
    ? !empty($body['checklist_risk_assessment']) : (bool) $booking['checklist_risk_assessment'];
$checklistShotList = array_key_exists('checklist_shot_list', $body)
    ? !empty($body['checklist_shot_list']) : (bool) $booking['checklist_shot_list'];
$checklistPreproductionCreative = array_key_exists('checklist_preproduction_creative', $body)
    ? !empty($body['checklist_preproduction_creative']) : (bool) $booking['checklist_preproduction_creative'];
$callSheetUrl = trim((string) ($body['checklist_call_sheet_url'] ?? ($booking['checklist_call_sheet_url'] ?? '')));
$riskAssessmentUrl = trim((string) ($body['checklist_risk_assessment_url'] ?? ($booking['checklist_risk_assessment_url'] ?? '')));
$shotListUrl = trim((string) ($body['checklist_shot_list_url'] ?? ($booking['checklist_shot_list_url'] ?? '')));
$preprodUrl = trim((string) ($body['checklist_preproduction_creative_url'] ?? ($booking['checklist_preproduction_creative_url'] ?? '')));
$skipCalendarSync = array_key_exists('skip_calendar_sync', $body)
    ? !empty($body['skip_calendar_sync']) : (bool) $booking['skip_calendar_sync'];
$kitSource = (string) ($body['kit_source'] ?? $booking['kit_source']);
if (!in_array($kitSource, ['fuzzy_duck', 'mark', 'tom'], true)) {
    $kitSource = $booking['kit_source'];
}

$userName = current_user_name() ?: $userEmail;
$callSheetBy = checklist_by_value($checklistCallSheet, (bool) $booking['checklist_call_sheet'], $booking['checklist_call_sheet_by'], $userName);
$riskBy = checklist_by_value($checklistRiskAssessment, (bool) $booking['checklist_risk_assessment'], $booking['checklist_risk_assessment_by'], $userName);
$shotListBy = checklist_by_value($checklistShotList, (bool) $booking['checklist_shot_list'], $booking['checklist_shot_list_by'], $userName);
$preprodBy = checklist_by_value($checklistPreproductionCreative, (bool) $booking['checklist_preproduction_creative'], $booking['checklist_preproduction_creative_by'], $userName);

function checklist_by_value(bool $newValue, bool $oldValue, ?string $existingBy, string $userName): ?string
{
    if ($newValue === $oldValue) {
        return $existingBy;
    }
    return $newValue ? $userName : null;
}

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

// Only block if the date is actually changing onto a blocked day — a
// booking that already existed there before the block was added stays
// editable.
$originalDate = substr((string) $booking['start_datetime'], 0, 10);
if ($startDt->format('Y-m-d') !== $originalDate) {
    $blockedStmt = $pdo->prepare('SELECT reason FROM blocked_days WHERE day = ?');
    $blockedStmt->execute([$startDt->format('Y-m-d')]);
    $blocked = $blockedStmt->fetchColumn();
    if ($blocked !== false) {
        json_error('This day is blocked for bookings: ' . ($blocked ?: 'no reason given'));
    }
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

if ($clientId !== null) {
    $clientCheck = $pdo->prepare('SELECT id FROM clients WHERE id = ? AND active = 1');
    $clientCheck->execute([$clientId]);
    if (!$clientCheck->fetch()) {
        json_error('Selected client is invalid or inactive');
    }
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'UPDATE bookings SET title = :title, location = :location, what3words = :what3words,
         client_id = :client_id, notes = :notes,
         start_datetime = :start, end_datetime = :end,
         checklist_call_sheet = :call_sheet, checklist_call_sheet_by = :call_sheet_by, checklist_call_sheet_url = :call_sheet_url,
         checklist_risk_assessment = :risk, checklist_risk_assessment_by = :risk_by, checklist_risk_assessment_url = :risk_url,
         checklist_shot_list = :shot_list, checklist_shot_list_by = :shot_list_by, checklist_shot_list_url = :shot_list_url,
         checklist_preproduction_creative = :preprod, checklist_preproduction_creative_by = :preprod_by, checklist_preproduction_creative_url = :preprod_url,
         skip_calendar_sync = :skip_sync, kit_source = :kit_source
         WHERE id = :id'
    );
    $stmt->execute([
        'title' => $title,
        'location' => $location ?: null,
        'what3words' => $what3words ?: null,
        'client_id' => $clientId,
        'notes' => $notes ?: null,
        'start' => $startDt->format('Y-m-d H:i:s'),
        'end' => $endDt->format('Y-m-d H:i:s'),
        'kit_source' => $kitSource,
        'call_sheet' => (int) $checklistCallSheet,
        'call_sheet_by' => $callSheetBy,
        'call_sheet_url' => $callSheetUrl ?: null,
        'risk' => (int) $checklistRiskAssessment,
        'risk_by' => $riskBy,
        'risk_url' => $riskAssessmentUrl ?: null,
        'shot_list' => (int) $checklistShotList,
        'shot_list_by' => $shotListBy,
        'shot_list_url' => $shotListUrl ?: null,
        'preprod' => (int) $checklistPreproductionCreative,
        'preprod_by' => $preprodBy,
        'preprod_url' => $preprodUrl ?: null,
        'skip_sync' => (int) $skipCalendarSync,
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

$freshBooking = array_merge($booking, [
    'title' => $title,
    'location' => $location ?: null,
    'notes' => $notes ?: null,
    'start_datetime' => $startDt->format('Y-m-d H:i:s'),
    'end_datetime' => $endDt->format('Y-m-d H:i:s'),
    'checklist_call_sheet' => $checklistCallSheet,
    'checklist_call_sheet_by' => $callSheetBy,
    'checklist_risk_assessment' => $checklistRiskAssessment,
    'checklist_risk_assessment_by' => $riskBy,
    'checklist_shot_list' => $checklistShotList,
    'checklist_shot_list_by' => $shotListBy,
    'checklist_preproduction_creative' => $checklistPreproductionCreative,
    'checklist_preproduction_creative_by' => $preprodBy,
    'skip_calendar_sync' => $skipCalendarSync,
]);

// If sync was just turned off (for a booking that already had a real
// calendar event), remove the stale event rather than leaving an
// unmanaged duplicate sitting on the calendar forever.
if ($skipCalendarSync && !$booking['skip_calendar_sync']) {
    delete_booking_calendar_events($pdo, $id);
}

$syncResults = sync_booking_calendar($pdo, $freshBooking);

json_ok(['id' => $id, 'sync_results' => $syncResults]);
