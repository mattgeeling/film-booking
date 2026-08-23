<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM bookings WHERE id = ? AND is_standalone_doc = 1');
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    json_error('Document not found', 404);
}

$body = json_body();
$date = trim((string) ($body['date'] ?? ''));
$startDt = DateTime::createFromFormat('Y-m-d', $date);
if (!$startDt) {
    json_error('Invalid date');
}

$update = $pdo->prepare('UPDATE bookings SET start_datetime = :start, end_datetime = :end WHERE id = :id');
$update->execute([
    'start' => $startDt->format('Y-m-d') . ' 09:00:00',
    'end' => $startDt->format('Y-m-d') . ' 17:00:00',
    'id' => $id,
]);

json_ok(['id' => $id]);
