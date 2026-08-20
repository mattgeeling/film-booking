<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/booking_sync.php';

$userEmail = require_login();
$body = json_body();

$title = trim((string) ($body['title'] ?? ''));
$location = trim((string) ($body['location'] ?? ''));
$what3words = trim((string) ($body['what3words'] ?? ''));
$clientId = !empty($body['client_id']) ? (int) $body['client_id'] : null;
$notes = trim((string) ($body['notes'] ?? ''));
$start = (string) ($body['start_datetime'] ?? '');
$end = (string) ($body['end_datetime'] ?? '');
$attendeeIds = array_values(array_unique(array_map('intval', $body['attendee_ids'] ?? [])));
$checklistCallSheet = !empty($body['checklist_call_sheet']);
$checklistRiskAssessment = !empty($body['checklist_risk_assessment']);
$checklistShotList = !empty($body['checklist_shot_list']);
$checklistPreproductionCreative = !empty($body['checklist_preproduction_creative']);
$callSheetUrl = trim((string) ($body['checklist_call_sheet_url'] ?? ''));
$riskAssessmentUrl = trim((string) ($body['checklist_risk_assessment_url'] ?? ''));
$shotListUrl = trim((string) ($body['checklist_shot_list_url'] ?? ''));
$preprodUrl = trim((string) ($body['checklist_preproduction_creative_url'] ?? ''));
$skipCalendarSync = !empty($body['skip_calendar_sync']);
$kitSource = (string) ($body['kit_source'] ?? 'fuzzy_duck');
if (!in_array($kitSource, ['fuzzy_duck', 'mark', 'tom'], true)) {
    $kitSource = 'fuzzy_duck';
}
$userName = current_user_name() ?: $userEmail;
$callSheetBy = $checklistCallSheet ? $userName : null;
$riskBy = $checklistRiskAssessment ? $userName : null;
$shotListBy = $checklistShotList ? $userName : null;
$preprodBy = $checklistPreproductionCreative ? $userName : null;

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

$blockedStmt = $pdo->prepare('SELECT reason FROM blocked_days WHERE day = ?');
$blockedStmt->execute([$startDt->format('Y-m-d')]);
$blocked = $blockedStmt->fetchColumn();
if ($blocked !== false) {
    json_error('This day is blocked for bookings: ' . ($blocked ?: 'no reason given'));
}

$placeholders = implode(',', array_fill(0, count($attendeeIds), '?'));
$check = $pdo->prepare("SELECT id FROM people WHERE id IN ($placeholders) AND active = 1");
$check->execute($attendeeIds);
$validIds = array_map('intval', array_column($check->fetchAll(), 'id'));
if (count($validIds) !== count($attendeeIds)) {
    json_error('One or more attendees are invalid or inactive');
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
        'INSERT INTO bookings (title, location, what3words, client_id, notes, start_datetime, end_datetime, status, created_by, created_by_name,
            checklist_call_sheet, checklist_call_sheet_by, checklist_call_sheet_url,
            checklist_risk_assessment, checklist_risk_assessment_by, checklist_risk_assessment_url,
            checklist_shot_list, checklist_shot_list_by, checklist_shot_list_url,
            checklist_preproduction_creative, checklist_preproduction_creative_by, checklist_preproduction_creative_url,
            skip_calendar_sync, kit_source)
         VALUES (:title, :location, :what3words, :client_id, :notes, :start, :end, "pencil", :created_by, :created_by_name,
            :call_sheet, :call_sheet_by, :call_sheet_url,
            :risk, :risk_by, :risk_url, :shot_list, :shot_list_by, :shot_list_url,
            :preprod, :preprod_by, :preprod_url, :skip_sync, :kit_source)'
    );
    $stmt->execute([
        'title' => $title,
        'location' => $location ?: null,
        'what3words' => $what3words ?: null,
        'client_id' => $clientId,
        'notes' => $notes ?: null,
        'start' => $startDt->format('Y-m-d H:i:s'),
        'end' => $endDt->format('Y-m-d H:i:s'),
        'created_by_name' => $userName,
        'created_by' => $userEmail,
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

$freshBooking = [
    'id' => $bookingId,
    'title' => $title,
    'location' => $location ?: null,
    'notes' => $notes ?: null,
    'start_datetime' => $startDt->format('Y-m-d H:i:s'),
    'end_datetime' => $endDt->format('Y-m-d H:i:s'),
    'status' => 'pencil',
    'checklist_call_sheet' => $checklistCallSheet,
    'checklist_call_sheet_by' => $callSheetBy,
    'checklist_risk_assessment' => $checklistRiskAssessment,
    'checklist_risk_assessment_by' => $riskBy,
    'checklist_shot_list' => $checklistShotList,
    'checklist_shot_list_by' => $shotListBy,
    'checklist_preproduction_creative' => $checklistPreproductionCreative,
    'checklist_preproduction_creative_by' => $preprodBy,
    'skip_calendar_sync' => $skipCalendarSync,
];
$syncResults = sync_booking_calendar($pdo, $freshBooking);

json_ok(['id' => $bookingId, 'sync_results' => $syncResults], 201);
