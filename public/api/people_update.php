<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$pdo = db();
$existing = $pdo->prepare('SELECT * FROM people WHERE id = ?');
$existing->execute([$id]);
$person = $existing->fetch();
if (!$person) {
    json_error('Person not found', 404);
}

$body = json_body();
$name = trim((string) ($body['name'] ?? $person['name']));
$role = trim((string) ($body['role'] ?? ($person['role'] ?? '')));
$email = trim((string) ($body['email'] ?? $person['email']));

if ($name === '') {
    json_error('Name is required');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('A valid email is required');
}

try {
    $stmt = $pdo->prepare('UPDATE people SET name = :name, role = :role, email = :email WHERE id = :id');
    $stmt->execute(['name' => $name, 'role' => $role ?: null, 'email' => $email, 'id' => $id]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_error('That email is already in the people list');
    }
    throw $e;
}

json_ok(['id' => $id]);
