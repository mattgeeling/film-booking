<?php

function require_login(): string
{
    $email = current_user_email();
    if ($email === null) {
        json_error('Not authenticated', 401);
    }
    return $email;
}

// Only these four can add or change anything; everyone else signed in
// (any Fuzzy Duck Google account) can view. Same list as the Ducknest.
const FULL_ACCESS = [
    'matt@fuzzyduck.co.uk',
    'kate.hughes@fuzzyduck.co.uk',
    'louis@fuzzyduck.co.uk',
    'greg@fuzzyduck.co.uk',
];

function can_edit(): bool
{
    $email = current_user_email();
    return $email !== null && in_array(strtolower(trim($email)), FULL_ACCESS, true);
}

/** For anything that adds or changes: signed in and one of the four. */
function require_editor(): string
{
    $email = require_login();
    if (!can_edit()) {
        json_error('View only — ask Matt, Kate, Louis or Greg to add or change bookings', 403);
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
 *
 * Uses Google's tokeninfo endpoint (which validates the signature and
 * expiry server-side) rather than the google/apiclient SDK, to avoid
 * pulling in a dependency with tens of thousands of files.
 */
function verify_google_id_token(string $idToken): ?array
{
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($status !== 200 || !$body) {
        return null;
    }

    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        return null;
    }

    $emailVerified = $payload['email_verified'] ?? null;
    if ($emailVerified !== true && $emailVerified !== 'true') {
        return null;
    }

    $cfg = app_config()['google'];
    if (($payload['aud'] ?? null) !== $cfg['oauth_web_client_id']) {
        return null;
    }

    $domain = $cfg['workspace_domain'];
    if ($domain !== '' && ($payload['hd'] ?? '') !== $domain) {
        return null;
    }

    return $payload;
}
