<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$docType = (string) ($_GET['type'] ?? '');
if (!in_array($docType, ['call_sheet', 'risk_assessment'], true)) {
    json_error('Invalid type');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT id, title, location, start_datetime, created_by, created_by_name, doc_type
     FROM bookings
     WHERE is_standalone_doc = 1 AND doc_type IN (?, ?)
     ORDER BY created_at DESC'
);
$stmt->execute([$docType, $docType === 'risk_assessment' ? 'install_ra' : $docType]);

json_ok(['docs' => $stmt->fetchAll()]);
