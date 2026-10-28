<?php

// Single sign-in from the Ducknest: the Ducknest hands over a short-lived
// pass (who, when it expires) signed with the integration key both tools
// share; a valid one signs that person in here and opens the calendar.
//   GET ?t=<base64url payload>.<hex HMAC-SHA256>

require_once __DIR__ . '/../../lib/bootstrap.php';

function sso_fail(string $why): void
{
    header('Location: ../index.html?sso=' . rawurlencode($why));
    exit;
}

$key = (string) (app_config()['integration']['key'] ?? '');
$token = (string) ($_GET['t'] ?? '');
if ($key === '' || !preg_match('/^([A-Za-z0-9_-]+)\.([0-9a-f]{64})$/', $token, $m)) {
    sso_fail('That sign-in link isn\'t valid — open Film plan from the Ducknest again');
}
if (!hash_equals(hash_hmac('sha256', $m[1], $key), $m[2])) {
    sso_fail('That sign-in link isn\'t valid — open Film plan from the Ducknest again');
}
$data = json_decode((string) base64_decode(strtr($m[1], '-_', '+/')), true);
if (!is_array($data) || empty($data['e']) || (int) ($data['x'] ?? 0) < time()) {
    sso_fail('That sign-in link has expired — open Film plan from the Ducknest again');
}
session_regenerate_id(true);
$_SESSION['user_email'] = (string) $data['e'];
$_SESSION['user_name'] = (string) ($data['n'] ?? $data['e']);
$page = preg_match('/^[a-z-]+\.html$/', (string) ($data['p'] ?? '')) ? $data['p'] : 'calendar.html';
header('Location: ../' . $page . (!empty($data['embed']) ? '?embed=1' : ''));
