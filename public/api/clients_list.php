<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === '1';

$sql = 'SELECT id, name, logo_path, active FROM clients';
if (!$includeInactive) {
    $sql .= ' WHERE active = 1';
}
$sql .= ' ORDER BY name ASC';

$rows = db()->query($sql)->fetchAll();
foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['active'] = (bool) $row['active'];
}
unset($row);

json_ok($rows);
