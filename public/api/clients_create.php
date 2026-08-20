<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/uploads.php';

require_login();

$name = trim((string) ($_POST['name'] ?? ''));
if ($name === '') {
    json_error('Name is required');
}

$logoPath = null;
if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    try {
        $logoPath = save_uploaded_image($_FILES['logo'], 'clients');
    } catch (Throwable $e) {
        json_error($e->getMessage());
    }
}

$stmt = db()->prepare('INSERT INTO clients (name, logo_path, active) VALUES (:name, :logo, 1)');
$stmt->execute(['name' => $name, 'logo' => $logoPath]);

json_ok(['id' => (int) db()->lastInsertId()], 201);
