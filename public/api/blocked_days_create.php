<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$userEmail = require_login();
$body = json_body();

$day = (string) ($body['day'] ?? '');
$reason = trim((string) ($body['reason'] ?? ''));

$dayDt = DateTime::createFromFormat('Y-m-d', $day);
if (!$dayDt) {
    json_error('Invalid day, expected YYYY-MM-DD');
}

try {
    $stmt = db()->prepare('INSERT INTO blocked_days (day, reason, created_by) VALUES (:day, :reason, :created_by)');
    $stmt->execute(['day' => $dayDt->format('Y-m-d'), 'reason' => $reason ?: null, 'created_by' => $userEmail]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_error('That day is already blocked');
    }
    throw $e;
}

json_ok(['id' => (int) db()->lastInsertId()], 201);
