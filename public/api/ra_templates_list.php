<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$pdo = db();
$stmt = $pdo->query('SELECT id, name, standard_arrangements FROM ra_templates ORDER BY name ASC');

json_ok(['templates' => array_map(function ($t) {
    $standard = json_decode((string) $t['standard_arrangements'], true);
    return ['id' => (int) $t['id'], 'name' => $t['name'], 'install' => isset($standard['rows'])];
}, $stmt->fetchAll())]);
