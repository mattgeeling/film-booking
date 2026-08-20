<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$userEmail = require_login();

$bookingId = (int) ($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    json_error('Missing or invalid booking_id');
}

$pdo = db();

$bookingStmt = $pdo->prepare('SELECT id FROM bookings WHERE id = ?');
$bookingStmt->execute([$bookingId]);
if (!$bookingStmt->fetch()) {
    json_error('Booking not found', 404);
}

$body = json_body();

$subtitle = trim((string) ($body['subtitle'] ?? ''));
$sections = is_array($body['sections'] ?? null) ? $body['sections'] : [];

$stmt = $pdo->prepare(
    'INSERT INTO shot_lists (booking_id, subtitle, sections, created_by)
     VALUES (:booking_id, :subtitle, :sections, :created_by)
     ON DUPLICATE KEY UPDATE
        subtitle = VALUES(subtitle),
        sections = VALUES(sections)'
);
$stmt->execute([
    'booking_id' => $bookingId,
    'subtitle' => $subtitle ?: null,
    'sections' => json_encode($sections),
    'created_by' => $userEmail,
]);

json_ok(['booking_id' => $bookingId]);
