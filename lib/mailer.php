<?php

/**
 * Emails each attendee once a booking is confirmed. Uses PHP's built-in
 * mail() rather than a mailer library/SMTP client, matching this project's
 * policy of keeping the vendor footprint minimal for fast SFTP deploys.
 */
function send_confirmation_emails(PDO $pdo, array $booking): array
{
    $id = (int) $booking['id'];

    $attendeeStmt = $pdo->prepare(
        'SELECT p.id, p.name, p.email
         FROM booking_people bp JOIN people p ON p.id = bp.person_id
         WHERE bp.booking_id = ?'
    );
    $attendeeStmt->execute([$id]);
    $attendees = $attendeeStmt->fetchAll();

    if (!$attendees) {
        return [];
    }

    $mailCfg = app_config()['mail'];
    $fromHeader = sprintf('%s <%s>', $mailCfg['from_name'], $mailCfg['from_email']);
    $timezone = new DateTimeZone(app_config()['timezone']);
    $start = new DateTime(str_replace(' ', 'T', $booking['start_datetime']), $timezone);
    $end = new DateTime(str_replace(' ', 'T', $booking['end_datetime']), $timezone);

    $checklist = [
        'Call Sheet' => !empty($booking['checklist_call_sheet']),
        'Risk Assessment' => !empty($booking['checklist_risk_assessment']),
        'Shot List' => !empty($booking['checklist_shot_list']),
        'Pre-production creative' => !empty($booking['checklist_preproduction_creative']),
    ];
    $checklistLines = [];
    foreach ($checklist as $label => $done) {
        $checklistLines[] = ($done ? '[x] ' : '[ ] ') . $label;
    }

    $subject = 'Booking confirmed: ' . $booking['title'] . ' - ' . $start->format('D j M');

    $results = [];
    foreach ($attendees as $attendee) {
        $others = array_values(array_filter($attendees, fn($a) => (int) $a['id'] !== (int) $attendee['id']));
        $otherNames = array_map(fn($a) => $a['name'], $others);

        $lines = [
            'Hi ' . $attendee['name'] . ',',
            '',
            'This booking has been confirmed:',
            '',
            $booking['title'],
            $start->format('l j F Y') . ', ' . $start->format('H:i') . '-' . $end->format('H:i'),
        ];
        if (!empty($booking['location'])) {
            $lines[] = 'Location: ' . $booking['location'];
        }
        if (!empty($booking['what3words'])) {
            $lines[] = 'what3words: ' . $booking['what3words'];
        }
        if ($otherNames) {
            $lines[] = 'With: ' . implode(', ', $otherNames);
        }
        if (!empty($booking['notes'])) {
            $lines[] = '';
            $lines[] = 'Notes: ' . $booking['notes'];
        }
        $lines[] = '';
        $lines[] = 'Pre-production checklist:';
        array_push($lines, ...$checklistLines);
        $lines[] = '';
        $lines[] = 'This has also been added to your Google Calendar.';

        $body = implode("\n", $lines);
        $headers = "From: {$fromHeader}\r\nContent-Type: text/plain; charset=UTF-8";

        try {
            $sent = mail($attendee['email'], $subject, $body, $headers);
            if (!$sent) {
                throw new RuntimeException('mail() returned false');
            }
            $results[] = ['person' => $attendee['name'], 'status' => 'sent'];
        } catch (Throwable $e) {
            $results[] = ['person' => $attendee['name'], 'status' => 'error', 'error' => $e->getMessage()];
        }
    }

    return $results;
}
