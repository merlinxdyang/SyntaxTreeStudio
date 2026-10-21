<?php
declare(strict_types=1);
$path = sys_get_temp_dir() . '/syntree-security-' . bin2hex(random_bytes(8)) . '.sqlite';
define('DB_PATH', $path);
register_shutdown_function(static function () use ($path): void { @unlink($path); });
require dirname(__DIR__) . '/src/db.php';
function security_assert(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
$pdo = db();
security_assert((int) $pdo->query('SELECT COUNT(*) FROM admin_accounts')->fetchColumn() === 0, 'No default administrator may be created.');
$hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
$pdo->prepare('INSERT INTO admin_accounts (username, password_hash) VALUES (?, ?)')->execute(['existing-admin', $hash]);
init_schema($pdo);
security_assert(find_standalone_admin('existing-admin')['password_hash'] === $hash, 'Schema initialization must preserve existing passwords.');
for ($i = 0; $i < 9; $i++) { record_admin_login_failure('admin', '192.0.2.1'); }
security_assert(!admin_login_rate_limited('admin', '192.0.2.1'), 'Nine failures remain below the threshold.');
record_admin_login_failure('admin', '192.0.2.1');
security_assert(admin_login_rate_limited('admin', '192.0.2.2'), 'Rotating IP must not bypass the account limit.');
security_assert(admin_login_rate_limited('other-admin', '192.0.2.1'), 'Rotating username must not bypass the source limit.');
security_assert(!admin_login_rate_limited('other-admin', '192.0.2.2'), 'Unrelated accounts and sources remain available.');
$pdo->exec("UPDATE admin_login_attempts SET created_at = datetime('now', '-16 minutes')");
security_assert(!admin_login_rate_limited('admin', '192.0.2.1'), 'Expired attempts must not block sign-in.');
$pdo->exec("UPDATE admin_login_attempts SET created_at = datetime('now', '-2 days')");
record_admin_login_failure('admin', '192.0.2.1');
security_assert((int) $pdo->query('SELECT COUNT(*) FROM admin_login_attempts')->fetchColumn() === 1, 'Old failure records must be pruned.');
fwrite(STDOUT, "security tests passed\n");
