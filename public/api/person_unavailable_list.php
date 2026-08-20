<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$personId = (int) ($_GET['person_id'] ?? 0);

$sql = 'SELECT u.id, u.person_id, u.day, u.period, u.reason, p.name AS person_name
        FROM person_unavailable_days u
        JOIN people p ON p.id = u.person_id';

if ($personId > 0) {
    $stmt = db()->prepare($sql . ' WHERE u.person_id = ? ORDER BY u.day ASC');
    $stmt->execute([$personId]);
} else {
    $stmt = db()->query($sql . ' ORDER BY u.day ASC');
}

$rows = $stmt->fetchAll();
foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['person_id'] = (int) $row['person_id'];
}
unset($row);

json_ok($rows);
