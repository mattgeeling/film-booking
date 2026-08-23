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

$dayInfo = trim((string) ($body['day_info'] ?? ''));
$locationContactName = trim((string) ($body['location_contact_name'] ?? ''));
$locationContactPhone = trim((string) ($body['location_contact_phone'] ?? ''));
$parkingNotes = trim((string) ($body['parking_notes'] ?? ''));
$weatherSummary = trim((string) ($body['weather_summary'] ?? ''));
$weatherIcons = is_array($body['weather_icons'] ?? null) ? $body['weather_icons'] : null;
$weatherLocation = trim((string) ($body['weather_location'] ?? ''));
$sunriseSunset = trim((string) ($body['sunrise_sunset'] ?? ''));
$weatherLocationOverride = trim((string) ($body['weather_location_override'] ?? ''));
$nearestAe = trim((string) ($body['nearest_ae'] ?? ''));
$locationMap = trim((string) ($body['location_map'] ?? ''));
$productionCrew = is_array($body['production_crew'] ?? null) ? $body['production_crew'] : [];
$clientContacts = is_array($body['client_contacts'] ?? null) ? $body['client_contacts'] : [];
$equipment = is_array($body['equipment'] ?? null) ? $body['equipment'] : [];
$schedule = is_array($body['schedule'] ?? null) ? $body['schedule'] : [];

$stmt = $pdo->prepare(
    'INSERT INTO call_sheets (
        booking_id, day_info, location_contact_name, location_contact_phone,
        parking_notes, weather_summary, weather_icons, weather_location, sunrise_sunset, weather_location_override, production_crew, client_contacts,
        equipment, schedule, nearest_ae, location_map, created_by
     ) VALUES (
        :booking_id, :day_info, :location_contact_name, :location_contact_phone,
        :parking_notes, :weather_summary, :weather_icons, :weather_location, :sunrise_sunset, :weather_location_override, :production_crew, :client_contacts,
        :equipment, :schedule, :nearest_ae, :location_map, :created_by
     )
     ON DUPLICATE KEY UPDATE
        day_info = VALUES(day_info),
        location_contact_name = VALUES(location_contact_name),
        location_contact_phone = VALUES(location_contact_phone),
        parking_notes = VALUES(parking_notes),
        weather_summary = VALUES(weather_summary),
        weather_icons = VALUES(weather_icons),
        weather_location = VALUES(weather_location),
        sunrise_sunset = VALUES(sunrise_sunset),
        weather_location_override = VALUES(weather_location_override),
        production_crew = VALUES(production_crew),
        client_contacts = VALUES(client_contacts),
        equipment = VALUES(equipment),
        schedule = VALUES(schedule),
        nearest_ae = VALUES(nearest_ae),
        location_map = VALUES(location_map)'
);
$stmt->execute([
    'booking_id' => $bookingId,
    'day_info' => $dayInfo ?: null,
    'location_contact_name' => $locationContactName ?: null,
    'location_contact_phone' => $locationContactPhone ?: null,
    'parking_notes' => $parkingNotes ?: null,
    'weather_summary' => $weatherSummary ?: null,
    'weather_icons' => $weatherIcons ? json_encode($weatherIcons) : null,
    'weather_location' => $weatherLocation ?: null,
    'sunrise_sunset' => $sunriseSunset ?: null,
    'weather_location_override' => $weatherLocationOverride ?: null,
    'production_crew' => json_encode($productionCrew),
    'client_contacts' => json_encode($clientContacts),
    'equipment' => json_encode($equipment),
    'schedule' => json_encode($schedule),
    'nearest_ae' => $nearestAe ?: null,
    'location_map' => $locationMap ?: null,
    'created_by' => $userEmail,
]);

json_ok(['booking_id' => $bookingId]);
