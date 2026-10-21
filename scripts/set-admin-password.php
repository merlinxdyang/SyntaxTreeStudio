<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/config.php';
require dirname(__DIR__) . '/src/db.php';

$username = trim((string) ($argv[1] ?? 'admin'));
$password = rtrim((string) fgets(STDIN), "\r\n");
if ($username === '' || strlen($username) > 120 || strlen($password) < 16) {
    fwrite(STDERR, "Supply a username and a password of at least 16 characters on standard input.\n");
    exit(1);
}
$pdo = db();
$pdo->prepare('
    INSERT INTO admin_accounts (username, password_hash)
    VALUES (:username, :hash)
    ON CONFLICT(username) DO UPDATE SET password_hash = excluded.password_hash, updated_at = CURRENT_TIMESTAMP
')->execute([':username' => $username, ':hash' => password_hash($password, PASSWORD_DEFAULT)]);
unset($password);
fwrite(STDOUT, "Administrator password set.\n");
