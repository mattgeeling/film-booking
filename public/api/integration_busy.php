<?php

// For Job Health's schedule: when people are busy in their Google Calendar
// between two dates, with each meeting's name ('Busy' if marked private) —
// lib/google_calendar.php busyTimes() says what's left out. No sign-in; instead the request must
// carry the key shared with Job Health (X-Integration-Key header).
// GET ?from=YYYY-MM-DD&to=YYYY-MM-DD&emails=a@fuzzyduck.co.uk,b@fuzzyduck.co.uk

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/synergist_job.php';
require_once __DIR__ . '/../../lib/google_calendar.php';

if (!integration_key_ok()) {
    json_error('Not allowed', 403);
}

$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) {
    json_error('from and to dates (YYYY-MM-DD) are required');
}
if ((strtotime($to) - strtotime($from)) / 86400 > 190) {
    json_error('At most about six months at a time');
}
// Only our own people's calendars.
$emails = array_values(array_unique(array_filter(array_map(fn($e) => strtolower(trim($e)), explode(',', (string) ($_GET['emails'] ?? ''))),
    fn($e) => preg_match('/^[a-z0-9._%+-]+@fuzzyduck\.co\.uk$/', $e))));
if (!$emails || count($emails) > 40) {
    json_error('Between 1 and 40 @fuzzyduck.co.uk emails are needed');
}

try {
    json_ok((new GoogleCalendarService())->busyTimes($emails, $from, $to));
} catch (Throwable $e) {
    json_error('Calendars could not be read: ' . $e->getMessage(), 502);
}
