<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$userEmail = require_login();
$body = json_body();

$personId = (int) ($body['person_id'] ?? 0);
$weekday = (int) ($body['weekday'] ?? 0);
$period = (string) ($body['period'] ?? 'all_day');
$reason = trim((string) ($body['reason'] ?? ''));

if ($personId <= 0) {
    json_error('Missing or invalid person_id');
}

if ($weekday < 1 || $weekday > 7) {
    json_error('Invalid weekday, expected 1 (Monday) to 7 (Sunday)');
}

if (!in_array($period, ['all_day', 'am', 'pm'], true)) {
    json_error('Invalid period, expected all_day, am, or pm');
}

$personStmt = db()->prepare('SELECT id FROM people WHERE id = ?');
$personStmt->execute([$personId]);
if (!$personStmt->fetch()) {
    json_error('Person not found', 404);
}

try {
    $stmt = db()->prepare(
        'INSERT INTO person_recurring_unavailability (person_id, weekday, period, reason, created_by)
         VALUES (:person_id, :weekday, :period, :reason, :created_by)'
    );
    $stmt->execute([
        'person_id' => $personId,
        'weekday' => $weekday,
        'period' => $period,
        'reason' => $reason ?: null,
        'created_by' => $userEmail,
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_error('That person already has a recurring rule for that day/period');
    }
    throw $e;
}

json_ok(['id' => (int) db()->lastInsertId()], 201);
