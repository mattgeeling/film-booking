<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$body = json_body();
$name = trim((string) ($body['name'] ?? ''));
$role = trim((string) ($body['role'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));

if ($name === '') {
    json_error('Name is required');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('A valid email is required');
}

try {
    $stmt = db()->prepare('INSERT INTO people (name, role, email, active) VALUES (:name, :role, :email, 1)');
    $stmt->execute(['name' => $name, 'role' => $role ?: null, 'email' => $email]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_error('That email is already in the people list');
    }
    throw $e;
}

json_ok(['id' => (int) db()->lastInsertId()], 201);
