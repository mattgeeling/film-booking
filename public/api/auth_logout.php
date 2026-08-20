<?php

require_once __DIR__ . '/../../lib/bootstrap.php';

$_SESSION = [];
session_destroy();

json_ok(['loggedOut' => true]);
