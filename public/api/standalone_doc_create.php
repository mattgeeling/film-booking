<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$userEmail = require_login();
$body = json_body();

$docType = (string) ($body['doc_type'] ?? '');
if (!in_array($docType, ['call_sheet', 'risk_assessment'], true)) {
    json_error('Invalid doc_type');
}

$title = trim((string) ($body['title'] ?? ''));
if ($title === '') {
    json_error('Title is required');
}
$date = trim((string) ($body['date'] ?? ''));
$startDt = DateTime::createFromFormat('Y-m-d', $date);
if (!$startDt) {
    json_error('Invalid date');
}
$location = trim((string) ($body['location'] ?? ''));
$what3words = trim((string) ($body['what3words'] ?? ''));

$userName = current_user_name() ?: $userEmail;

$pdo = db();
$stmt = $pdo->prepare(
    'INSERT INTO bookings (
        title, location, what3words, start_datetime, end_datetime, status,
        is_standalone_doc, doc_type, created_by, created_by_name
     ) VALUES (
        :title, :location, :what3words, :start, :end, "pencil",
        1, :doc_type, :created_by, :created_by_name
     )'
);
$stmt->execute([
    'title' => $title,
    'location' => $location ?: null,
    'what3words' => $what3words ?: null,
    'start' => $startDt->format('Y-m-d') . ' 09:00:00',
    'end' => $startDt->format('Y-m-d') . ' 17:00:00',
    'doc_type' => $docType,
    'created_by' => $userEmail,
    'created_by_name' => $userName,
]);

json_ok(['id' => (int) $pdo->lastInsertId()], 201);
