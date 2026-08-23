<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/booking_query.php';

require_login();

$pdo = db();

$stmt = $pdo->query(
    'SELECT ' . BOOKING_SELECT_COLUMNS . ',
        CASE WHEN b.checklist_risk_assessment = 1
             AND (ra.signoff_director_date IS NULL OR ra.signoff_producer_date IS NULL)
             THEN 1 ELSE 0 END AS ra_unsigned
     FROM bookings b
     LEFT JOIN clients c ON c.id = b.client_id
     LEFT JOIN risk_assessments ra ON ra.booking_id = b.id
     WHERE b.status != "cancelled"
       AND b.is_standalone_doc = 0
       AND b.end_datetime >= NOW()
       AND (
         b.checklist_call_sheet = 0
         OR b.checklist_risk_assessment = 0
         OR (b.checklist_shot_list = 0 AND b.checklist_shot_list_na = 0)
         OR b.checklist_preproduction_creative = 0
         OR (b.checklist_risk_assessment = 1 AND (ra.signoff_director_date IS NULL OR ra.signoff_producer_date IS NULL))
       )
     ORDER BY b.start_datetime ASC'
);
$rows = $stmt->fetchAll();
$raUnsignedById = [];
foreach ($rows as $row) {
    $raUnsignedById[(int) $row['id']] = (bool) $row['ra_unsigned'];
}
$bookings = hydrate_bookings($pdo, $rows);
foreach ($bookings as &$booking) {
    $booking['ra_unsigned'] = $raUnsignedById[$booking['id']] ?? false;
}
unset($booking);

json_ok(['bookings' => $bookings]);
