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

if (!defined('SYNTREE_SKIP_VISITOR_LOG') || SYNTREE_SKIP_VISITOR_LOG !== true) {
    record_visitor_event();
}
