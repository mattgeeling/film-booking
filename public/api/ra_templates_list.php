<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$pdo = db();
$stmt = $pdo->query('SELECT id, name FROM ra_templates ORDER BY name ASC');

json_ok(['templates' => $stmt->fetchAll()]);
