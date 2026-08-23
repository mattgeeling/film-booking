<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$pdo = db();
$stmt = $pdo->prepare('DELETE FROM ra_templates WHERE id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    json_error('Template not found', 404);
}

json_ok(['id' => $id]);
