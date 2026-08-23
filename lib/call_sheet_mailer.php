<?php

/**
 * Emails the crew a branded HTML version of the call sheet. Uses PHP's
 * built-in mail() rather than a mailer library/SMTP client, matching this
 * project's policy of keeping the vendor footprint minimal for fast SFTP
 * deploys.
 */
function build_call_sheet_email_context(PDO $pdo, int $bookingId): array
{
    $bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch();
    if (!$booking) {
        throw new RuntimeException('Booking not found');
    }

    $sheetStmt = $pdo->prepare('SELECT * FROM call_sheets WHERE booking_id = ?');
    $sheetStmt->execute([$bookingId]);
    $sheet = $sheetStmt->fetch();
    if (!$sheet) {
        throw new RuntimeException('No call sheet has been saved for this booking yet');
    }

    $productionCrew = $sheet['production_crew'] ? json_decode($sheet['production_crew'], true) : [];
    $clientContacts = $sheet['client_contacts'] ? json_decode($sheet['client_contacts'], true) : [];
    $equipment = $sheet['equipment'] ? json_decode($sheet['equipment'], true) : [];
    $schedule = $sheet['schedule'] ? json_decode($sheet['schedule'], true) : [];

    $recipients = [];
    foreach (array_merge($productionCrew, $clientContacts) as $row) {
        $email = trim((string) ($row['email'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[$email] = ['name' => $name ?: $email, 'email' => $email];
        }
    }

    $cfg = app_config();
    $timezone = new DateTimeZone($cfg['timezone']);
    $start = new DateTime(str_replace(' ', 'T', $booking['start_datetime']), $timezone);
    $logoUrl = rtrim($cfg['base_url'], '/') . '/fuzzy-duck-logo.png';
    $subject = 'Call sheet: ' . $booking['title'] . ' - ' . $start->format('D j M');

    return [
        'booking' => $booking,
        'sheet' => $sheet,
        'productionCrew' => $productionCrew,
        'clientContacts' => $clientContacts,
        'equipment' => $equipment,
        'schedule' => $schedule,
        'recipients' => array_values($recipients),
        'start' => $start,
        'logoUrl' => $logoUrl,
        'subject' => $subject,
        'mailCfg' => $cfg['mail'],
    ];
}

function preview_call_sheet_email(PDO $pdo, int $bookingId): array
{
    $ctx = build_call_sheet_email_context($pdo, $bookingId);
    $html = render_call_sheet_email_html($ctx);

    return [
        'html' => $html,
        'recipients' => $ctx['recipients'],
        'subject' => $ctx['subject'],
    ];
}

function send_call_sheet_email(PDO $pdo, int $bookingId): array
{
    $ctx = build_call_sheet_email_context($pdo, $bookingId);
    if (!$ctx['recipients']) {
        return [];
    }

    $html = render_call_sheet_email_html($ctx);
    $fromHeader = sprintf('%s <%s>', $ctx['mailCfg']['from_name'], $ctx['mailCfg']['from_email']);
    $headers = "From: {$fromHeader}\r\nContent-Type: text/html; charset=UTF-8";

    $results = [];
    foreach ($ctx['recipients'] as $recipient) {
        try {
            $sent = mail($recipient['email'], $ctx['subject'], $html, $headers);
            if (!$sent) {
                throw new RuntimeException('mail() returned false');
            }
            $results[] = ['person' => $recipient['name'], 'status' => 'sent'];
        } catch (Throwable $e) {
            $results[] = ['person' => $recipient['name'], 'status' => 'error', 'error' => $e->getMessage()];
        }
    }

    return $results;
}

function render_call_sheet_email_html(array $ctx): string
{
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $booking = $ctx['booking'];
    $sheet = $ctx['sheet'];
    $start = $ctx['start'];

    $personTable = function (array $rows) use ($e) {
        if (!$rows) {
            return '<p style="font-size:13px;color:#9ca3af;margin:4px 0;">None added.</p>';
        }
        $rowsHtml = '';
        foreach ($rows as $r) {
            $rowsHtml .= '<tr>'
                . '<td style="padding:5px 8px;border-bottom:1px solid #f0f0f0;font-size:13px;">' . $e($r['name'] ?? '') . '</td>'
                . '<td style="padding:5px 8px;border-bottom:1px solid #f0f0f0;font-size:13px;">' . $e($r['title'] ?? '') . '</td>'
                . '<td style="padding:5px 8px;border-bottom:1px solid #f0f0f0;font-size:13px;">' . $e($r['contact'] ?? '') . '</td>'
                . '<td style="padding:5px 8px;border-bottom:1px solid #f0f0f0;font-size:13px;">' . $e($r['call_time'] ?? '') . '</td>'
                . '</tr>';
        }
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr style="background:#f5f5f5;">'
            . '<td style="padding:5px 8px;font-size:12px;font-weight:700;">Name</td>'
            . '<td style="padding:5px 8px;font-size:12px;font-weight:700;">Title</td>'
            . '<td style="padding:5px 8px;font-size:12px;font-weight:700;">Contact</td>'
            . '<td style="padding:5px 8px;font-size:12px;font-weight:700;">Call time</td>'
            . '</tr>' . $rowsHtml . '</table>';
    };

    $box = function (string $title, string $bodyHtml) use ($e) {
        return '<tr><td style="padding:8px 24px;">'
            . '<div style="border:1px solid #222;border-radius:4px;overflow:hidden;">'
            . '<div style="background:#f5cf82;padding:6px;text-align:center;font-weight:700;font-size:13px;">' . $e($title) . '</div>'
            . '<div style="padding:10px 14px;">' . $bodyHtml . '</div>'
            . '</div></td></tr>';
    };

    $locationBody = '';
    if (!empty($booking['location'])) {
        $locationBody .= '<p style="margin:2px 0;font-size:13px;"><strong>Address:</strong> ' . $e($booking['location']) . '</p>';
    }
    if (!empty($sheet['location_contact_name'])) {
        $locationBody .= '<p style="margin:2px 0;font-size:13px;"><strong>Contact on arrival:</strong> ' . $e($sheet['location_contact_name']) . ($sheet['location_contact_phone'] ? ' — ' . $e($sheet['location_contact_phone']) : '') . '</p>';
    }
    if (!empty($sheet['parking_notes'])) {
        $locationBody .= '<p style="margin:2px 0;font-size:13px;"><strong>Parking:</strong> ' . nl2br($e($sheet['parking_notes'])) . '</p>';
    }
    if (!empty($sheet['location_map'])) {
        $locationBody .= '<img src="' . $e($sheet['location_map']) . '" width="480" alt="Map of the shoot location" style="max-width:100%;border-radius:4px;margin-top:6px;display:block;">';
    }
    if ($locationBody === '') {
        $locationBody = '<p style="font-size:13px;color:#9ca3af;margin:2px 0;">No location details added.</p>';
    }

    $boxes = $box('Location', $locationBody);

    if (!empty($sheet['weather_summary'])) {
        $weatherBody = nl2br($e($sheet['weather_summary']));
        if (!empty($sheet['sunrise_sunset'])) {
            $weatherBody .= '<p style="margin:6px 0 0;font-size:13px;font-weight:600;">' . $e($sheet['sunrise_sunset']) . '</p>';
        }
        $boxes .= $box('Weather', $weatherBody);
    }

    $boxes .= $box('Production', $personTable($ctx['productionCrew']));
    if ($ctx['clientContacts']) {
        $boxes .= $box('Client', $personTable($ctx['clientContacts']));
    }

    if ($ctx['equipment']) {
        $eqRows = '';
        foreach ($ctx['equipment'] as $r) {
            $eqRows .= '<tr><td style="padding:5px 8px;border-bottom:1px solid #f0f0f0;font-size:13px;width:30%;">' . $e($r['supplier'] ?? '') . '</td>'
                . '<td style="padding:5px 8px;border-bottom:1px solid #f0f0f0;font-size:13px;">' . nl2br($e($r['items'] ?? '')) . '</td></tr>';
        }
        $boxes .= $box('Supplier &amp; Equipment', '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $eqRows . '</table>');
    }

    if ($ctx['schedule']) {
        $schedRows = '';
        foreach ($ctx['schedule'] as $r) {
            $schedRows .= '<tr><td style="padding:5px 8px;border-bottom:1px solid #f0f0f0;font-size:13px;width:30%;">' . $e($r['time'] ?? '') . '</td>'
                . '<td style="padding:5px 8px;border-bottom:1px solid #f0f0f0;font-size:13px;">' . $e($r['description'] ?? '') . '</td></tr>';
        }
        $boxes .= $box('Schedule', '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $schedRows . '</table>');
    }

    if (!empty($sheet['nearest_ae'])) {
        $boxes .= $box('Nearest A&amp;E', nl2br($e($sheet['nearest_ae'])));
    }

    $dayInfo = !empty($sheet['day_info']) ? $e($sheet['day_info']) . ' — ' : '';

    return '<!doctype html>
<html>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">
          <tr>
            <td style="background:#ffd300;padding:18px 24px;text-align:center;">
              <img src="' . $e($ctx['logoUrl']) . '" alt="Fuzzy Duck" height="40" style="display:block;margin:0 auto 6px;border:0;">
              <div style="font-weight:700;font-size:16px;color:#111;">CALL SHEET</div>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 24px 8px;">
              <h1 style="margin:0 0 4px;font-size:19px;color:#111;">' . $e($booking['title']) . '</h1>
              <p style="margin:0;font-size:13px;color:#444;">' . $dayInfo . $e($start->format('l j F Y')) . '</p>
            </td>
          </tr>
          ' . $boxes . '
          <tr>
            <td style="background:#111111;padding:14px 24px;font-size:12px;color:#ffd300;text-align:center;">
              Film Plan &middot; Fuzzy Duck
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}
