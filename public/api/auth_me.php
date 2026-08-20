<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$email = current_user_email();
if ($email === null) {
    json_error('Not authenticated', 401);
}

json_ok(['email' => $email, 'name' => current_user_name() ?? $email]);
