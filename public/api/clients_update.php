<?php

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/uploads.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing or invalid id');
}

$pdo = db();
$existing = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$existing->execute([$id]);
$client = $existing->fetch();
if (!$client) {
    json_error('Client not found', 404);
}

$name = trim((string) ($_POST['name'] ?? $client['name']));
if ($name === '') {
    json_error('Name is required');
}

$logoPath = $client['logo_path'];
if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    try {
        $newLogoPath = save_uploaded_image($_FILES['logo'], 'clients');
    } catch (Throwable $e) {
        json_error($e->getMessage());
    }
    delete_uploaded_file($logoPath);
    $logoPath = $newLogoPath;
}

$pdo->prepare('UPDATE clients SET name = :name, logo_path = :logo WHERE id = :id')
    ->execute(['name' => $name, 'logo' => $logoPath, 'id' => $id]);

json_ok(['id' => $id]);
