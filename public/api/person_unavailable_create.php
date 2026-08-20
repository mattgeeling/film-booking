<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$userEmail = require_login();
$body = json_body();

$personId = (int) ($body['person_id'] ?? 0);
$day = (string) ($body['day'] ?? '');
$period = (string) ($body['period'] ?? 'all_day');
$reason = trim((string) ($body['reason'] ?? ''));

if ($personId <= 0) {
    json_error('Missing or invalid person_id');
}

if (!in_array($period, ['all_day', 'am', 'pm'], true)) {
    json_error('Invalid period, expected all_day, am, or pm');
}

$dayDt = DateTime::createFromFormat('Y-m-d', $day);
if (!$dayDt) {
    json_error('Invalid day, expected YYYY-MM-DD');
}

$personStmt = db()->prepare('SELECT id FROM people WHERE id = ?');
$personStmt->execute([$personId]);
if (!$personStmt->fetch()) {
    json_error('Person not found', 404);
}

try {
    $stmt = db()->prepare(
        'INSERT INTO person_unavailable_days (person_id, day, period, reason, created_by)
         VALUES (:person_id, :day, :period, :reason, :created_by)'
    );
    $stmt->execute([
        'person_id' => $personId,
        'day' => $dayDt->format('Y-m-d'),
        'period' => $period,
        'reason' => $reason ?: null,
        'created_by' => $userEmail,
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_error('That person is already marked unavailable for that period on that day');
    }
    throw $e;
}

json_ok(['id' => (int) db()->lastInsertId()], 201);
