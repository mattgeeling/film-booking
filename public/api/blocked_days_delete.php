<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

db()->prepare('DELETE FROM blocked_days WHERE id = ?')->execute([$id]);

json_ok(['id' => $id]);
