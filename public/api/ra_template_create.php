<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$userEmail = require_login();
$body = json_body();

$name = trim((string) ($body['name'] ?? ''));
if ($name === '') {
    json_error('Name is required');
}
$standardArrangements = is_array($body['standard_arrangements'] ?? null) ? $body['standard_arrangements'] : [];
$hazards = is_array($body['hazards'] ?? null) ? $body['hazards'] : [];

$pdo = db();
$stmt = $pdo->prepare(
    'INSERT INTO ra_templates (name, standard_arrangements, hazards, created_by)
     VALUES (:name, :standard_arrangements, :hazards, :created_by)'
);
$stmt->execute([
    'name' => $name,
    'standard_arrangements' => json_encode($standardArrangements),
    'hazards' => json_encode($hazards),
    'created_by' => $userEmail,
]);

json_ok(['id' => (int) $pdo->lastInsertId()], 201);
