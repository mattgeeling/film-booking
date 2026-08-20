<?php

/**
 * Emails attendees a branded HTML confirmation for a booking. Uses PHP's
 * built-in mail() rather than a mailer library/SMTP client, matching this
 * project's policy of keeping the vendor footprint minimal for fast SFTP
 * deploys.
 */
function build_confirmation_email_context(PDO $pdo, array $booking): array
{
    $id = (int) $booking['id'];

    $attendeeStmt = $pdo->prepare(
        'SELECT p.id, p.name, p.email
         FROM booking_people bp JOIN people p ON p.id = bp.person_id
         WHERE bp.booking_id = ?'
    );
    $attendeeStmt->execute([$id]);
    $attendees = $attendeeStmt->fetchAll();

    $cfg = app_config();
    $timezone = new DateTimeZone($cfg['timezone']);
    $start = new DateTime(str_replace(' ', 'T', $booking['start_datetime']), $timezone);
    $end = new DateTime(str_replace(' ', 'T', $booking['end_datetime']), $timezone);
    $logoUrl = rtrim($cfg['base_url'], '/') . '/fuzzy-duck-logo.png';

    $checklist = [
        'Call Sheet' => ['done' => !empty($booking['checklist_call_sheet']), 'url' => $booking['checklist_call_sheet_url'] ?? null],
        'Risk Assessment' => ['done' => !empty($booking['checklist_risk_assessment']), 'url' => $booking['checklist_risk_assessment_url'] ?? null],
        'Shot List' => ['done' => !empty($booking['checklist_shot_list']), 'url' => $booking['checklist_shot_list_url'] ?? null, 'na' => !empty($booking['checklist_shot_list_na'])],
        'Pre-production creative' => ['done' => !empty($booking['checklist_preproduction_creative']), 'url' => $booking['checklist_preproduction_creative_url'] ?? null],
        'Additional documents' => ['done' => !empty($booking['checklist_additional_documents']), 'url' => $booking['checklist_additional_documents_url'] ?? null],
    ];

    $subject = 'Booking confirmed: ' . $booking['title'] . ' - ' . $start->format('D j M');

    return [
        'attendees' => $attendees,
        'start' => $start,
        'end' => $end,
        'logoUrl' => $logoUrl,
        'checklist' => $checklist,
        'subject' => $subject,
        'mailCfg' => $cfg['mail'],
    ];
}

/**
 * Emails attendees a branded HTML confirmation for a booking. Uses PHP's
 * built-in mail() rather than a mailer library/SMTP client, matching this
 * project's policy of keeping the vendor footprint minimal for fast SFTP
 * deploys.
 */
function send_confirmation_emails(PDO $pdo, array $booking): array
{
    $ctx = build_confirmation_email_context($pdo, $booking);
    $attendees = $ctx['attendees'];

    if (!$attendees) {
        return [];
    }

    $fromHeader = sprintf('%s <%s>', $ctx['mailCfg']['from_name'], $ctx['mailCfg']['from_email']);

    $results = [];
    foreach ($attendees as $attendee) {
        $others = array_values(array_filter($attendees, fn($a) => (int) $a['id'] !== (int) $attendee['id']));
        $otherNames = array_map(fn($a) => $a['name'], $others);

        $html = render_confirmation_email_html($attendee['name'], $booking, $ctx['start'], $ctx['end'], $otherNames, $ctx['checklist'], $ctx['logoUrl']);
        $headers = "From: {$fromHeader}\r\nContent-Type: text/html; charset=UTF-8";

        try {
            $sent = mail($attendee['email'], $ctx['subject'], $html, $headers);
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

/**
 * Renders the confirmation email as a representative preview (from the
 * first attendee's point of view) without sending anything.
 */
function preview_confirmation_email(PDO $pdo, array $booking): array
{
    $ctx = build_confirmation_email_context($pdo, $booking);
    $attendees = $ctx['attendees'];

    if (!$attendees) {
        return ['html' => null, 'recipients' => [], 'subject' => $ctx['subject']];
    }

    $first = $attendees[0];
    $others = array_values(array_filter($attendees, fn($a) => (int) $a['id'] !== (int) $first['id']));
    $otherNames = array_map(fn($a) => $a['name'], $others);

    $html = render_confirmation_email_html($first['name'], $booking, $ctx['start'], $ctx['end'], $otherNames, $ctx['checklist'], $ctx['logoUrl']);

    return [
        'html' => $html,
        'recipients' => array_map(fn($a) => ['name' => $a['name'], 'email' => $a['email']], $attendees),
        'subject' => $ctx['subject'],
    ];
}

function render_confirmation_email_html(
    string $attendeeName,
    array $booking,
    DateTime $start,
    DateTime $end,
    array $otherNames,
    array $checklist,
    string $logoUrl
): string {
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

    $checklistRows = '';
    foreach ($checklist as $label => $item) {
        if (!empty($item['na'])) {
            $icon = '&#8212;';
            $iconColor = '#9ca3af';
            $labelHtml = $e($label) . ' <span style="color:#9ca3af;font-weight:400;">(not required)</span>';
            $link = '';
        } else {
            $done = !empty($item['done']);
            $icon = $done ? '&#10003;' : '&#9675;';
            $iconColor = $done ? '#1f6b3a' : '#c7ccd4';
            $labelHtml = $e($label);
            $link = $item['url']
                ? '<div style="margin-top:2px;"><a href="' . $e($item['url']) . '" style="color:#8a6d00;font-size:12px;text-decoration:none;">&#128279; Open document</a></div>'
                : '';
        }
        $checklistRows .= '
          <tr>
            <td style="padding:6px 0;border-bottom:1px solid #f0f0f0;vertical-align:top;width:22px;">
              <span style="display:inline-block;width:18px;height:18px;line-height:18px;text-align:center;border-radius:50%;background:' . $iconColor . ';color:#fff;font-size:11px;">' . $icon . '</span>
            </td>
            <td style="padding:6px 0 6px 10px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#1f2430;">' . $labelHtml . $link . '</td>
          </tr>';
    }

    $statusLabel = ($booking['status'] ?? '') === 'confirmed' ? 'CONFIRMED' : strtoupper((string) ($booking['status'] ?? ''));

    $detailRows = '';
    $addDetail = function (string $label, string $value) use (&$detailRows, $e) {
        if ($value === '') {
            return;
        }
        $detailRows .= '
          <tr>
            <td style="padding:3px 0;font-size:13px;color:#6b7280;width:110px;vertical-align:top;">' . $e($label) . '</td>
            <td style="padding:3px 0;font-size:13px;color:#1f2430;">' . $value . '</td>
          </tr>';
    };

    $addDetail('When', $e($start->format('l j F Y')) . ' &middot; ' . $e($start->format('H:i')) . '&ndash;' . $e($end->format('H:i')));
    if (!empty($booking['location'])) {
        $addDetail('Location', $e($booking['location']));
    }
    if (!empty($booking['what3words'])) {
        $addDetail('what3words', $e($booking['what3words']));
    }
    if ($otherNames) {
        $addDetail('With', $e(implode(', ', $otherNames)));
    }
    if (!empty($booking['notes'])) {
        $addDetail('Notes', nl2br($e($booking['notes'])));
    }

    return '<!doctype html>
<html>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:560px;width:100%;">
          <tr>
            <td style="background:#ffd300;padding:18px 24px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="vertical-align:middle;">
                    <img src="' . $e($logoUrl) . '" alt="Fuzzy Duck" height="32" style="display:block;border:0;">
                  </td>
                  <td style="vertical-align:middle;text-align:right;font-weight:700;font-size:15px;color:#111111;">Film Plan</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 24px 8px;">
              <span style="display:inline-block;background:#dcf5e3;color:#1f6b3a;font-size:11px;font-weight:700;letter-spacing:0.04em;padding:3px 9px;border-radius:999px;">' . $e($statusLabel) . '</span>
              <h1 style="margin:12px 0 4px;font-size:20px;color:#111111;">' . $e($booking['title']) . '</h1>
              <p style="margin:0 0 16px;font-size:14px;color:#1f2430;">Hi ' . $e($attendeeName) . ', this shoot has been confirmed.</p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $detailRows . '</table>
            </td>
          </tr>
          <tr>
            <td style="padding:8px 24px 24px;">
              <h2 style="margin:16px 0 4px;font-size:13px;text-transform:uppercase;letter-spacing:0.04em;color:#6b7280;">Pre-production checklist</h2>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $checklistRows . '</table>
            </td>
          </tr>
          <tr>
            <td style="background:#111111;padding:14px 24px;font-size:12px;color:#ffd300;text-align:center;">
              This has also been added to your Google Calendar &middot; Film Plan &middot; Fuzzy Duck
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}
