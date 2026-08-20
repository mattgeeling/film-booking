<?php

function require_login(): string
{
    $email = current_user_email();
    if ($email === null) {
        json_error('Not authenticated', 401);
    }
    return $email;
}

function current_user_email(): ?string
{
    return $_SESSION['user_email'] ?? null;
}

function current_user_name(): ?string
{
    return $_SESSION['user_name'] ?? null;
}

/**
 * Verifies a Google Identity Services ID token and enforces that it belongs
 * to the configured Workspace domain. Returns the token payload, or null if
 * the token is invalid, unverified, or from the wrong domain.
 */
function verify_google_id_token(string $idToken): ?array
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $cfg = app_config()['google'];
    $client = new Google\Client(['client_id' => $cfg['oauth_web_client_id']]);

    $payload = $client->verifyIdToken($idToken);
    if (!$payload || empty($payload['email_verified'])) {
        return null;
    }

    $domain = $cfg['workspace_domain'];
    if ($domain !== '' && ($payload['hd'] ?? '') !== $domain) {
        return null;
    }

    return $payload;
}
