<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$userEmail = require_login();

$bookingId = (int) ($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    json_error('Missing or invalid booking_id');
}

$pdo = db();

$bookingStmt = $pdo->prepare('SELECT id FROM bookings WHERE id = ?');
$bookingStmt->execute([$bookingId]);
if (!$bookingStmt->fetch()) {
    json_error('Booking not found', 404);
}

$body = json_body();

$clientName = trim((string) ($body['client_name'] ?? ''));
$locationContact = trim((string) ($body['location_contact'] ?? ''));
$directorName = trim((string) ($body['director_name'] ?? ''));
$directorEmail = trim((string) ($body['director_email'] ?? ''));
$directorMobile = trim((string) ($body['director_mobile'] ?? ''));
$pmName = trim((string) ($body['production_manager_name'] ?? ''));
$pmEmail = trim((string) ($body['production_manager_email'] ?? ''));
$pmMobile = trim((string) ($body['production_manager_mobile'] ?? ''));
$briefDescription = trim((string) ($body['brief_description'] ?? ''));
$crewExperts = trim((string) ($body['crew_experts'] ?? ''));
$nearestAe = trim((string) ($body['nearest_ae'] ?? ''));
$standardArrangements = is_array($body['standard_arrangements'] ?? null) ? $body['standard_arrangements'] : [];
$hazards = is_array($body['hazards'] ?? null) ? $body['hazards'] : [];
$signoffDirectorName = trim((string) ($body['signoff_director_name'] ?? ''));
$signoffDirectorDate = trim((string) ($body['signoff_director_date'] ?? ''));
$signoffProducerName = trim((string) ($body['signoff_producer_name'] ?? ''));
$signoffProducerDate = trim((string) ($body['signoff_producer_date'] ?? ''));

$stmt = $pdo->prepare(
    'INSERT INTO risk_assessments (
        booking_id, client_name, location_contact, director_name, director_email, director_mobile,
        production_manager_name, production_manager_email, production_manager_mobile,
        brief_description, crew_experts, nearest_ae, standard_arrangements, hazards,
        signoff_director_name, signoff_director_date, signoff_producer_name, signoff_producer_date,
        created_by
     ) VALUES (
        :booking_id, :client_name, :location_contact, :director_name, :director_email, :director_mobile,
        :production_manager_name, :production_manager_email, :production_manager_mobile,
        :brief_description, :crew_experts, :nearest_ae, :standard_arrangements, :hazards,
        :signoff_director_name, :signoff_director_date, :signoff_producer_name, :signoff_producer_date,
        :created_by
     )
     ON DUPLICATE KEY UPDATE
        client_name = VALUES(client_name),
        location_contact = VALUES(location_contact),
        director_name = VALUES(director_name),
        director_email = VALUES(director_email),
        director_mobile = VALUES(director_mobile),
        production_manager_name = VALUES(production_manager_name),
        production_manager_email = VALUES(production_manager_email),
        production_manager_mobile = VALUES(production_manager_mobile),
        brief_description = VALUES(brief_description),
        crew_experts = VALUES(crew_experts),
        nearest_ae = VALUES(nearest_ae),
        standard_arrangements = VALUES(standard_arrangements),
        hazards = VALUES(hazards),
        signoff_director_name = VALUES(signoff_director_name),
        signoff_director_date = VALUES(signoff_director_date),
        signoff_producer_name = VALUES(signoff_producer_name),
        signoff_producer_date = VALUES(signoff_producer_date)'
);
$stmt->execute([
    'booking_id' => $bookingId,
    'client_name' => $clientName ?: null,
    'location_contact' => $locationContact ?: null,
    'director_name' => $directorName ?: null,
    'director_email' => $directorEmail ?: null,
    'director_mobile' => $directorMobile ?: null,
    'production_manager_name' => $pmName ?: null,
    'production_manager_email' => $pmEmail ?: null,
    'production_manager_mobile' => $pmMobile ?: null,
    'brief_description' => $briefDescription ?: null,
    'crew_experts' => $crewExperts ?: null,
    'nearest_ae' => $nearestAe ?: null,
    'standard_arrangements' => json_encode($standardArrangements),
    'hazards' => json_encode($hazards),
    'signoff_director_name' => $signoffDirectorName ?: null,
    'signoff_director_date' => $signoffDirectorDate ?: null,
    'signoff_producer_name' => $signoffProducerName ?: null,
    'signoff_producer_date' => $signoffProducerDate ?: null,
    'created_by' => $userEmail,
]);

json_ok(['booking_id' => $bookingId]);
