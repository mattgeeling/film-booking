<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$monthParam = $_GET['month'] ?? date('Y-m');
$start = DateTime::createFromFormat('Y-m-d', $monthParam . '-01');
if (!$start) {
    json_error('Invalid month, expected YYYY-MM');
}
$start->setTime(0, 0, 0);
$end = (clone $start)->modify('+1 month');

$stmt = db()->prepare(
    'SELECT id, title, kit_source, start_datetime, end_datetime, status
     FROM bookings
     WHERE status != "cancelled"
       AND is_standalone_doc = 0
       AND (doc_type IS NULL OR doc_type <> "install")
       AND start_datetime >= :start
       AND start_datetime < :end
     ORDER BY start_datetime ASC'
);
$stmt->execute([
    'start' => $start->format('Y-m-d H:i:s'),
    'end' => $end->format('Y-m-d H:i:s'),
]);
$rows = $stmt->fetchAll();

$counts = ['fuzzy_duck' => 0, 'mark' => 0, 'tom' => 0];
$daysSeen = ['fuzzy_duck' => [], 'mark' => [], 'tom' => []];
$entries = [];
foreach ($rows as $row) {
    $kit = $row['kit_source'];
    $last = substr($row['end_datetime'], 0, 10);
    for ($day = substr($row['start_datetime'], 0, 10); $day <= $last; $day = date('Y-m-d', strtotime($day . ' +1 day'))) {
        if (isset($daysSeen[$kit]) && !isset($daysSeen[$kit][$day])) {
            $daysSeen[$kit][$day] = true;
            $counts[$kit]++;
        }
    }
    $entries[] = [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'kit_source' => $kit,
        'start_datetime' => $row['start_datetime'],
        'end_datetime' => $row['end_datetime'],
        'status' => $row['status'],
    ];
}

json_ok(['month' => $start->format('Y-m'), 'counts' => $counts, 'entries' => $entries]);
