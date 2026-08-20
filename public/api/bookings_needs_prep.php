<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/booking_query.php';

require_login();

$pdo = db();

$stmt = $pdo->query(
    'SELECT ' . BOOKING_SELECT_COLUMNS . '
     FROM bookings b
     LEFT JOIN clients c ON c.id = b.client_id
     WHERE b.status != "cancelled"
       AND b.end_datetime >= NOW()
       AND (
         b.checklist_call_sheet = 0
         OR b.checklist_risk_assessment = 0
         OR (b.checklist_shot_list = 0 AND b.checklist_shot_list_na = 0)
         OR b.checklist_preproduction_creative = 0
       )
     ORDER BY b.start_datetime ASC'
);
$bookings = hydrate_bookings($pdo, $stmt->fetchAll());

json_ok(['bookings' => $bookings]);
