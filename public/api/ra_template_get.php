<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM ra_templates WHERE id = ?');
$stmt->execute([$id]);
$template = $stmt->fetch();
if (!$template) {
    json_error('Template not found', 404);
}

json_ok([
    'id' => (int) $template['id'],
    'name' => $template['name'],
    'standard_arrangements' => $template['standard_arrangements'] ? json_decode($template['standard_arrangements'], true) : [],
    'hazards' => $template['hazards'] ? json_decode($template['hazards'], true) : [],
]);
