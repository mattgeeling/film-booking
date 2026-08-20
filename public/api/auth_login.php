<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$body = json_body();
$idToken = (string) ($body['id_token'] ?? '');
if ($idToken === '') {
    json_error('Missing id_token');
}

$payload = verify_google_id_token($idToken);
if (!$payload) {
    json_error('Sign-in rejected: use your Fuzzy Duck Workspace account', 403);
}

$email = $payload['email'];
$name = $payload['name'] ?? $email;

$stmt = db()->prepare(
    'INSERT INTO app_users (email, name, last_login_at) VALUES (:email, :name, NOW())
     ON DUPLICATE KEY UPDATE name = VALUES(name), last_login_at = NOW()'
);
$stmt->execute(['email' => $email, 'name' => $name]);

session_regenerate_id(true);
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = $name;

json_ok(['email' => $email, 'name' => $name]);
