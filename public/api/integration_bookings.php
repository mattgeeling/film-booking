<?php

// For Job Health: shoots between two dates (confirmed and pencilled — not
// cancelled), with their Synergist job number and who's on them, so Job
// Health can put them on its schedule. No sign-in; instead the request must
// carry the key shared with Job Health (X-Integration-Key header).

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/synergist_job.php';

if (!integration_key_ok()) {
    json_error('Not allowed', 403);
}

$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    json_error('from and to dates (YYYY-MM-DD) are required');
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT b.id, b.title, b.status, b.synergist_job_number, b.start_datetime, b.end_datetime, c.name AS client_name
     FROM bookings b LEFT JOIN clients c ON c.id = b.client_id
     WHERE b.is_standalone_doc = 0 AND b.status IN ('pencil', 'confirmed')
       AND b.start_datetime < DATE_ADD(?, INTERVAL 1 DAY) AND b.end_datetime >= ?
     ORDER BY b.start_datetime"
);
$stmt->execute([$to, $from]);
$bookings = $stmt->fetchAll();

$people = [];
if ($bookings) {
    $ids = array_column($bookings, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $att = $pdo->prepare("SELECT bp.booking_id, p.name, p.email FROM booking_people bp JOIN people p ON p.id = bp.person_id WHERE bp.booking_id IN ($in)");
    $att->execute($ids);
    foreach ($att->fetchAll() as $a) {
        $people[$a['booking_id']][] = ['name' => $a['name'], 'email' => $a['email']];
    }
}

$base = rtrim((string) (app_config()['base_url'] ?? ''), '/');
json_ok(array_map(fn($b) => [
    'id' => (int) $b['id'],
    'title' => $b['title'],
    'status' => $b['status'],
    'job_number' => $b['synergist_job_number'],
    'client_name' => $b['client_name'],
    'start' => $b['start_datetime'],
    'end' => $b['end_datetime'],
    'people' => $people[$b['id']] ?? [],
    'url' => $base !== '' ? $base . '/calendar.html?date=' . substr($b['start_datetime'], 0, 10) : null,
], $bookings));
