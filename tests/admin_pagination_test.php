<?php
declare(strict_types=1);

$databasePath = sys_get_temp_dir() . '/syntree-admin-pagination-' . bin2hex(random_bytes(6)) . '.sqlite';
define('DB_PATH', $databasePath);
register_shutdown_function(static function () use ($databasePath): void {
    @unlink($databasePath);
});

require dirname(__DIR__) . '/src/db.php';

function assert_admin_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$pdo = db();
$userStmt = $pdo->prepare('INSERT INTO users (name, email) VALUES (:name, :email)');
$feedbackStmt = $pdo->prepare('INSERT INTO feedback_messages (name, email, message) VALUES (:name, :email, :message)');
$visitorStmt = $pdo->prepare('INSERT INTO visitor_events (session_key, ip_address, country_code, country_name, path) VALUES (:session, :ip, :code, :country, :path)');
$enrichmentStmt = $pdo->prepare('INSERT INTO ip_enrichment (ip_hash, ip_address, institution_guess, institution_type, confidence) VALUES (:hash, :ip, :institution, :type, :confidence)');

for ($i = 1; $i <= 45; $i++) {
    $ip = '203.0.113.' . $i;
    $userStmt->execute([':name' => 'User ' . $i, ':email' => 'user' . $i . '@example.test']);
    $feedbackStmt->execute([':name' => 'Guest ' . $i, ':email' => 'guest' . $i . '@example.test', ':message' => 'Feedback ' . $i]);
    $visitorStmt->execute([':session' => 'session-' . $i, ':ip' => $ip, ':code' => 'C' . $i, ':country' => 'Country ' . $i, ':path' => '/page-' . $i]);
    $enrichmentStmt->execute([':hash' => hash('sha256', $ip), ':ip' => $ip, ':institution' => 'Institution ' . $i, ':type' => 'academic', ':confidence' => 80]);
}

assert_admin_same(45, admin_user_count(), 'User pagination must count the full dataset.');
assert_admin_same(20, count(admin_user_rows(20, 20)), 'User pagination must honor limit and offset.');
assert_admin_same(45, feedback_messages_count(), 'Feedback pagination must count the full dataset.');
assert_admin_same(20, count(recent_feedback_messages(20, 20)), 'Feedback pagination must honor limit and offset.');
assert_admin_same(45, visitor_country_summary_count(), 'Country pagination must count the full dataset.');
assert_admin_same(20, count(visitor_country_summary(null, 20, 20)), 'Country pagination must honor limit and offset.');
assert_admin_same(45, institution_summary_count('all'), 'Institution pagination must count the full dataset.');
assert_admin_same(20, count(institution_summary_rows('all', 20, 20)), 'Institution pagination must honor limit and offset.');
assert_admin_same(45, visitor_events_count(), 'Visitor pagination must count all records without a date cutoff.');
assert_admin_same(20, count(recent_visitor_events(20, null, 20)), 'Visitor pagination must honor limit and offset.');

$adminSource = file_get_contents(dirname(__DIR__) . '/admin/admin.php');
assert_admin_same(true, str_contains($adminSource, "[20, 40, 100]"), 'The admin page-size selector must allow 20, 40, or 100 rows.');
assert_admin_same(false, str_contains($adminSource, "ADMIN_VISITOR_DAYS"), 'Admin data pages must not retain the three-day cutoff.');

fwrite(STDOUT, "admin pagination tests passed\n");
