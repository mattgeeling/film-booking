<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$personId = (int) ($_GET['person_id'] ?? 0);

$sql = 'SELECT r.id, r.person_id, r.weekday, r.interval_weeks, r.anchor_date, r.period, r.reason, p.name AS person_name
        FROM person_recurring_unavailability r
        JOIN people p ON p.id = r.person_id';

if ($personId > 0) {
    $stmt = db()->prepare($sql . ' WHERE r.person_id = ? ORDER BY r.weekday ASC');
    $stmt->execute([$personId]);
} else {
    $stmt = db()->query($sql . ' ORDER BY r.weekday ASC');
}

$rows = $stmt->fetchAll();
foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['person_id'] = (int) $row['person_id'];
    $row['weekday'] = (int) $row['weekday'];
    $row['interval_weeks'] = (int) $row['interval_weeks'];
}
unset($row);

json_ok($rows);
