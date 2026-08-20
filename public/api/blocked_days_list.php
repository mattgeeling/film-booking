<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$rows = db()->query('SELECT id, day, reason FROM blocked_days ORDER BY day ASC')->fetchAll();
foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
}
unset($row);

json_ok($rows);
