<?php

function save_uploaded_image(array $file, string $subdir, int $maxBytes = 2 * 1024 * 1024): string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No image uploaded or upload failed');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image is too large (max 2MB)');
    }

    $info = getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('File is not a valid image');
    }

    $mimeToExt = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $mimeToExt[$info['mime']] ?? null;
    if (!$ext) {
        throw new RuntimeException('Unsupported image type (use PNG, JPEG, GIF, or WebP)');
    }

    $dir = __DIR__ . '/../public/uploads/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create upload directory');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('Failed to save uploaded image');
    }

    return 'uploads/' . $subdir . '/' . $filename;
}

function delete_uploaded_file(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }
    $full = __DIR__ . '/../public/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}
