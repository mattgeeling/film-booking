<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$body = json_body();
$active = array_key_exists('active', $body) ? (int) (bool) $body['active'] : 0;

$pdo = db();
$existing = $pdo->prepare('SELECT id FROM people WHERE id = ?');
$existing->execute([$id]);
if (!$existing->fetch()) {
    json_error('Person not found', 404);
}

$pdo->prepare('UPDATE people SET active = :active WHERE id = :id')
    ->execute(['active' => $active, 'id' => $id]);

json_ok(['id' => $id, 'active' => (bool) $active]);
