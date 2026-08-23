<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$body = json_body();
$name = trim((string) ($body['name'] ?? ''));
$role = trim((string) ($body['role'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$isMainShooter = !empty($body['is_main_shooter']);

if ($name === '') {
    json_error('Name is required');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('A valid email is required');
}

try {
    $stmt = db()->prepare('INSERT INTO people (name, role, email, is_main_shooter, active) VALUES (:name, :role, :email, :is_main_shooter, 1)');
    $stmt->execute(['name' => $name, 'role' => $role ?: null, 'email' => $email, 'is_main_shooter' => (int) $isMainShooter]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_error('That email is already in the people list');
    }
    throw $e;
}

json_ok(['id' => (int) db()->lastInsertId()], 201);
