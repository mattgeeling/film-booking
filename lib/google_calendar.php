<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

final class GoogleCalendarService
{
    private ?Google\Client $client = null;
    private bool $dryRun;

    public function __construct()
    {
        $cfg = app_config();
        $this->dryRun = (bool) $cfg['calendar_dry_run'];

        if (!$this->dryRun) {
            $this->client = new Google\Client();
            $this->client->setAuthConfig($cfg['google']['service_account_key_path']);
            $this->client->setScopes(['https://www.googleapis.com/auth/calendar.events']);
        }
    }

    /**
     * @return string Google Calendar event ID
     */
    public function createEvent(string $personEmail, array $booking): string
    {
        $payload = $this->eventPayload($booking);

        if ($this->dryRun) {
            error_log('[calendar_dry_run] createEvent for ' . $personEmail . ': ' . json_encode($payload));
            return 'dry-run-' . bin2hex(random_bytes(8));
        }

        $service = $this->serviceFor($personEmail);
        $event = new Google\Service\Calendar\Event($payload);
        $created = $service->events->insert('primary', $event);
        return $created->getId();
    }

    public function updateEvent(string $personEmail, string $eventId, array $booking): void
    {
        $payload = $this->eventPayload($booking);

        if ($this->dryRun) {
            error_log("[calendar_dry_run] updateEvent {$eventId} for {$personEmail}: " . json_encode($payload));
            return;
        }

        $service = $this->serviceFor($personEmail);
        $event = new Google\Service\Calendar\Event($payload);
        $service->events->patch('primary', $eventId, $event);
    }

    public function deleteEvent(string $personEmail, string $eventId): void
    {
        if ($this->dryRun) {
            error_log("[calendar_dry_run] deleteEvent {$eventId} for {$personEmail}");
            return;
        }

        $service = $this->serviceFor($personEmail);
        try {
            $service->events->delete('primary', $eventId);
        } catch (Google\Service\Exception $e) {
            if ($e->getCode() !== 404 && $e->getCode() !== 410) {
                throw $e;
            }
        }
    }

    private function serviceFor(string $personEmail): Google\Service\Calendar
    {
        $this->client->setSubject($personEmail);
        return new Google\Service\Calendar($this->client);
    }

    private function eventPayload(array $booking): array
    {
        $timezone = app_config()['timezone'];
        $otherNames = array_map(fn($a) => $a['name'], $booking['other_attendees'] ?? []);
        $description = trim(($booking['notes'] ?? '') . ($otherNames ? "\n\nWith: " . implode(', ', $otherNames) : ''));

        return [
            'summary' => 'FUZZY DUCK FILMING: ' . $booking['title'],
            'location' => $booking['location'] ?? '',
            'description' => $description,
            'start' => [
                'dateTime' => str_replace(' ', 'T', $booking['start_datetime']),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => str_replace(' ', 'T', $booking['end_datetime']),
                'timeZone' => $timezone,
            ],
        ];
    }
}
