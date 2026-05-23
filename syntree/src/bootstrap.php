<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_start();

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/oauth.php';

db();

