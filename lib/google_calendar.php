<?php

require_once __DIR__ . '/config.php';

/**
 * Talks to the Google Calendar REST API directly over cURL, authenticating
 * as a service account via the JWT-bearer flow (domain-wide delegation),
 * using only PHP's built-in curl/openssl extensions. Deliberately avoids
 * google/apiclient — that package pulls in tens of thousands of files
 * (one class per Google API), which makes file-by-file SFTP deploys to
 * shared hosting impractically slow.
 */
final class GoogleCalendarService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_BASE = 'https://www.googleapis.com';
    private const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    private bool $dryRun;
    private ?array $credentials = null;
    private array $tokenCache = [];

    public function __construct()
    {
        $cfg = app_config();
        $this->dryRun = (bool) $cfg['calendar_dry_run'];

        if (!$this->dryRun) {
            $json = file_get_contents($cfg['google']['service_account_key_path']);
            $this->credentials = json_decode($json, true);
            if (!is_array($this->credentials) || empty($this->credentials['private_key'])) {
                throw new RuntimeException('Invalid or missing service account key file');
            }
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

        $result = $this->apiRequest($personEmail, 'POST', '/calendar/v3/calendars/primary/events', $payload);
        return $result['id'];
    }

    public function updateEvent(string $personEmail, string $eventId, array $booking): void
    {
        $payload = $this->eventPayload($booking);

        if ($this->dryRun) {
            error_log("[calendar_dry_run] updateEvent {$eventId} for {$personEmail}: " . json_encode($payload));
            return;
        }

        $this->apiRequest($personEmail, 'PATCH', '/calendar/v3/calendars/primary/events/' . rawurlencode($eventId), $payload);
    }

    public function deleteEvent(string $personEmail, string $eventId): void
    {
        if ($this->dryRun) {
            error_log("[calendar_dry_run] deleteEvent {$eventId} for {$personEmail}");
            return;
        }

        try {
            $this->apiRequest($personEmail, 'DELETE', '/calendar/v3/calendars/primary/events/' . rawurlencode($eventId), null);
        } catch (RuntimeException $e) {
            if (!str_contains($e->getMessage(), '(404)') && !str_contains($e->getMessage(), '(410)')) {
                throw $e;
            }
        }
    }

    private function eventPayload(array $booking): array
    {
        $timezone = app_config()['timezone'];
        $otherNames = array_map(fn($a) => $a['name'], $booking['other_attendees'] ?? []);

        $checklist = [
            'Call Sheet' => ['done' => !empty($booking['checklist_call_sheet']), 'by' => $booking['checklist_call_sheet_by'] ?? null, 'url' => $booking['checklist_call_sheet_url'] ?? null],
            'Risk Assessment' => ['done' => !empty($booking['checklist_risk_assessment']), 'by' => $booking['checklist_risk_assessment_by'] ?? null, 'url' => $booking['checklist_risk_assessment_url'] ?? null],
            'Shot List' => ['done' => !empty($booking['checklist_shot_list']), 'by' => $booking['checklist_shot_list_by'] ?? null, 'url' => $booking['checklist_shot_list_url'] ?? null, 'na' => !empty($booking['checklist_shot_list_na'])],
            'Pre-production creative' => ['done' => !empty($booking['checklist_preproduction_creative']), 'by' => $booking['checklist_preproduction_creative_by'] ?? null, 'url' => $booking['checklist_preproduction_creative_url'] ?? null],
            'Additional documents' => ['done' => !empty($booking['checklist_additional_documents']), 'by' => $booking['checklist_additional_documents_by'] ?? null, 'url' => $booking['checklist_additional_documents_url'] ?? null],
        ];
        $checklistLines = [];
        foreach ($checklist as $label => $item) {
            if (!empty($item['na'])) {
                $checklistLines[] = '➖ ' . $label . ' — Not required';
                continue;
            }
            $line = ($item['done'] ? '✅' : '⬜') . ' ' . $label;
            if ($item['done'] && $item['by']) {
                $line .= ' — ' . $item['by'];
            }
            if ($item['url']) {
                $line .= ' (' . $item['url'] . ')';
            }
            $checklistLines[] = $line;
        }

        $parts = array_filter([
            $booking['notes'] ?? '',
            $otherNames ? 'With: ' . implode(', ', $otherNames) : '',
            "Pre-production checklist:\n" . implode("\n", $checklistLines),
        ]);
        $description = implode("\n\n", $parts);
        $isPencil = ($booking['status'] ?? 'confirmed') === 'pencil';

        return [
            'summary' => (in_array($booking['doc_type'] ?? null, ['install', 'install_ra'], true) ? 'FUZZY DUCK INSTALL' : 'FUZZY DUCK FILMING') . ($isPencil ? ' (PENCIL)' : '') . ': ' . $booking['title'],
            'location' => $booking['location'] ?? '',
            'description' => $description,
            'status' => $isPencil ? 'tentative' : 'confirmed',
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

    /**
     * For Job Health's schedule: when each person is busy between two dates
     * (YYYY-MM-DD, inclusive), from their primary calendar: times and the
     * meeting's name ('Busy' for events marked private). Left out: all-day events, ones marked "free", ones they've
     * declined, cancelled ones, out-of-office and working-location entries,
     * and our own FUZZY DUCK FILMING / INSTALL events (already on the schedule).
     * Every calendar is read at once (curl_multi), so many people stay quick.
     *
     * @return array email => ['busy' => [['start' => 'Y-m-d H:i', 'end' => 'Y-m-d H:i', 'title' => string], …], 'error' => ?string]
     */
    public function busyTimes(array $emails, string $from, string $to): array
    {
        $out = [];
        if ($this->dryRun) {
            foreach ($emails as $email) {
                $out[$email] = ['busy' => [], 'error' => null];
            }
            return $out;
        }
        $tz = new DateTimeZone(app_config()['timezone']);
        $timeMin = (new DateTime($from . ' 00:00:00', $tz))->format(DATE_RFC3339);
        $timeMax = (new DateTime($to . ' 23:59:59', $tz))->format(DATE_RFC3339);

        // 1. A token for each person, all at once.
        $tokenReqs = [];
        foreach ($emails as $email) {
            $tokenReqs[$email] = [self::TOKEN_URL, 'POST', http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwtFor($email),
            ]), ['Content-Type: application/x-www-form-urlencoded']];
        }
        $tokens = [];
        foreach ($this->curlMulti($tokenReqs) as $email => [$status, $body]) {
            $data = json_decode((string) $body, true);
            if ($status === 200 && isset($data['access_token'])) {
                $tokens[$email] = $data['access_token'];
            } else {
                $out[$email] = ['busy' => [], 'error' => "Couldn't sign in to their calendar ({$status})"];
            }
        }

        // 2. Their events, all at once.
        $query = http_build_query([
            'timeMin' => $timeMin, 'timeMax' => $timeMax, 'singleEvents' => 'true', 'orderBy' => 'startTime',
            'maxResults' => 2500, 'timeZone' => app_config()['timezone'],
            'fields' => 'items(status,summary,visibility,transparency,eventType,start,end,attendees(self,responseStatus))',
        ]);
        $listReqs = [];
        foreach ($tokens as $email => $token) {
            $listReqs[$email] = [self::API_BASE . '/calendar/v3/calendars/primary/events?' . $query, 'GET', null, ['Authorization: Bearer ' . $token]];
        }
        foreach ($this->curlMulti($listReqs) as $email => [$status, $body]) {
            $data = json_decode((string) $body, true);
            if ($status !== 200 || !is_array($data)) {
                $out[$email] = ['busy' => [], 'error' => "Couldn't read their calendar ({$status})"];
                continue;
            }
            $busy = [];
            foreach ($data['items'] ?? [] as $ev) {
                if (empty($ev['start']['dateTime']) || empty($ev['end']['dateTime'])) {
                    continue; // all day
                }
                if (($ev['status'] ?? '') === 'cancelled' || ($ev['transparency'] ?? '') === 'transparent'
                    || in_array($ev['eventType'] ?? 'default', ['outOfOffice', 'workingLocation'], true)) {
                    continue;
                }
                if (preg_match('/^FUZZY DUCK (FILMING|INSTALL)/i', (string) ($ev['summary'] ?? ''))) {
                    continue;
                }
                $declined = false;
                foreach ($ev['attendees'] ?? [] as $a) {
                    if (!empty($a['self']) && ($a['responseStatus'] ?? '') === 'declined') {
                        $declined = true;
                    }
                }
                if ($declined) {
                    continue;
                }
                $private = in_array($ev['visibility'] ?? 'default', ['private', 'confidential'], true);
                $title = trim((string) ($ev['summary'] ?? ''));
                $busy[] = [
                    'start' => (new DateTime($ev['start']['dateTime']))->setTimezone($tz)->format('Y-m-d H:i'),
                    'end' => (new DateTime($ev['end']['dateTime']))->setTimezone($tz)->format('Y-m-d H:i'),
                    'title' => $private || $title === '' ? 'Busy' : mb_substr($title, 0, 120),
                ];
            }
            $out[$email] = ['busy' => $busy, 'error' => null];
        }
        return $out;
    }

    /** Several requests at once: key => [url, method, body, headers] → key => [status, body]. */
    private function curlMulti(array $requests): array
    {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($requests as $key => [$url, $method, $body, $headers]) {
            $ch = curl_init($url);
            $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20];
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = $body;
            }
            curl_setopt_array($ch, $opts);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);
        $out = [];
        foreach ($handles as $key => $ch) {
            $out[$key] = [(int) curl_getinfo($ch, CURLINFO_HTTP_CODE), (string) curl_multi_getcontent($ch)];
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);
        return $out;
    }

    /** The signed request for a token acting as $subject. */
    private function jwtFor(string $subject): string
    {
        $now = time();
        $segments = [
            $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode(json_encode([
                'iss' => $this->credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
                'sub' => $subject,
            ])),
        ];
        $signature = '';
        $signingInput = implode('.', $segments);
        if (!openssl_sign($signingInput, $signature, $this->credentials['private_key'], 'sha256WithRSAEncryption')) {
            throw new RuntimeException('Failed to sign JWT for Google service account');
        }
        $segments[] = $this->base64UrlEncode($signature);
        return implode('.', $segments);
    }

    private function accessTokenFor(string $subject): string
    {
        $cached = $this->tokenCache[$subject] ?? null;
        if ($cached && $cached['expires'] > time() + 30) {
            return $cached['token'];
        }

        $now = time();
        $segments = [
            $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode(json_encode([
                'iss' => $this->credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
                'sub' => $subject,
            ])),
        ];

        $signature = '';
        $signingInput = implode('.', $segments);
        if (!openssl_sign($signingInput, $signature, $this->credentials['private_key'], 'sha256WithRSAEncryption')) {
            throw new RuntimeException('Failed to sign JWT for Google service account');
        }
        $segments[] = $this->base64UrlEncode($signature);
        $jwt = implode('.', $segments);

        [$status, $body] = $this->curlRequest(self::TOKEN_URL, 'POST', http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        $data = json_decode((string) $body, true);
        if ($status !== 200 || !isset($data['access_token'])) {
            throw new RuntimeException("Google token request failed ({$status}): {$body}");
        }

        $this->tokenCache[$subject] = [
            'token' => $data['access_token'],
            'expires' => $now + (int) ($data['expires_in'] ?? 3600),
        ];
        return $data['access_token'];
    }

    private function apiRequest(string $subject, string $method, string $path, ?array $body): array
    {
        $token = $this->accessTokenFor($subject);
        $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
        $payload = $body !== null ? json_encode($body) : null;

        [$status, $responseBody] = $this->curlRequest(self::API_BASE . $path, $method, $payload, $headers);

        if ($status >= 300) {
            throw new RuntimeException("Google Calendar API error ({$status}): {$responseBody}");
        }

        return $responseBody !== '' ? json_decode((string) $responseBody, true) : [];
    }

    private function curlRequest(string $url, string $method, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            throw new RuntimeException('cURL request to Google failed: ' . $error);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [$status, $responseBody];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
