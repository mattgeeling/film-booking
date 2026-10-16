<?php

// Live Synergist jobs, for the booking form's "Synergist job" box — read
// from Job Health (which already syncs them), so Film Plan doesn't need its
// own Synergist login. Saved for 10 minutes.

require_once __DIR__ . '/../../lib/bootstrap.php';

require_login();

$cfg = app_config()['integration'] ?? [];
$url = rtrim((string) ($cfg['job_health_url'] ?? ''), '/');
$key = (string) ($cfg['key'] ?? '');
if ($url === '' || $key === '') {
    json_ok(['jobs' => [], 'note' => 'Job Health link not set up']);
}

$cacheFile = sys_get_temp_dir() . '/film-plan-jobs-' . md5($url) . '.json';
if (is_file($cacheFile) && filemtime($cacheFile) > time() - 600) {
    json_ok(['jobs' => json_decode((string) file_get_contents($cacheFile), true) ?: []]);
}

$ch = curl_init($url . '/api/integration_jobs.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['X-Integration-Key: ' . $key],
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$data = $status === 200 ? json_decode((string) $body, true) : null;
if (!is_array($data) || empty($data['ok'])) {
    // Fall back to the last saved list rather than an empty box.
    $old = is_file($cacheFile) ? (json_decode((string) file_get_contents($cacheFile), true) ?: []) : [];
    json_ok(['jobs' => $old, 'note' => "Couldn't reach Job Health"]);
}
@file_put_contents($cacheFile, json_encode($data['jobs']));
json_ok(['jobs' => $data['jobs']]);
