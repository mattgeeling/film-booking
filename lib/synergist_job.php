<?php

/**
 * A Synergist job number as typed ("12422", "1/12422", "JOB 1/00012422") →
 * Synergist's own form, "1/00012422". Null when blank or not a job number.
 */
function normalize_synergist_job(string $input): ?string
{
    $s = strtoupper(trim($input));
    $s = preg_replace('/^JOB\s*/', '', $s);
    if (!preg_match('/^(?:(\d+)\/)?0*(\d{1,8})$/', $s, $m)) {
        return null;
    }
    return ($m[1] !== '' ? $m[1] : '1') . '/' . str_pad($m[2], 8, '0', STR_PAD_LEFT);
}

/** True when the request carries the key shared with Job Health. */
function integration_key_ok(): bool
{
    $key = (string) (app_config()['integration']['key'] ?? '');
    $given = (string) ($_SERVER['HTTP_X_INTEGRATION_KEY'] ?? '');
    return $key !== '' && $given !== '' && hash_equals($key, $given);
}
