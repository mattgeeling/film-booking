<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === '1';

$sql = 'SELECT id, name, role, email, is_main_shooter, active FROM people';
if (!$includeInactive) {
    $sql .= ' WHERE active = 1';
}
$sql .= ' ORDER BY name ASC';

$rows = db()->query($sql)->fetchAll();
foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['active'] = (bool) $row['active'];
    $row['is_main_shooter'] = (bool) $row['is_main_shooter'];
}
unset($row);

json_ok($rows);
