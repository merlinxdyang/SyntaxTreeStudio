<?php
declare(strict_types=1);

$databasePath = sys_get_temp_dir() . '/syntree-feedback-bbs-' . bin2hex(random_bytes(6)) . '.sqlite';
define('DB_PATH', $databasePath);
register_shutdown_function(static function () use ($databasePath): void {
    @unlink($databasePath);
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require dirname(__DIR__) . '/src/helpers.php';
require dirname(__DIR__) . '/src/db.php';

function bbs_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n");
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . "\n");
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

function bbs_assert_true(bool $actual, string $message): void
{
    bbs_assert_same(true, $actual, $message);
}

$requiredFunctions = [
    'public_feedback_messages',
    'public_feedback_messages_count',
    'feedback_submission_violation',
    'publish_feedback_message',
    'save_admin_feedback_reply',
    'edit_feedback_message_by_admin',
    'soft_delete_feedback_message',
    'restore_feedback_message',
    'purge_feedback_message',
    'feedback_render_markdown',
    'login_attempt_rate_limited',
    'registration_rate_limited',
];
foreach ($requiredFunctions as $function) {
    bbs_assert_true(function_exists($function), "Missing BBS function: {$function}");
}

$pdo = db();
init_schema($pdo);
$columns = array_column($pdo->query('PRAGMA table_info(feedback_messages)')->fetchAll(), 'name');
foreach (['status', 'published_at', 'edited_at', 'deleted_at'] as $column) {
    bbs_assert_true(in_array($column, $columns, true), "feedback_messages must contain {$column}.");
}

$pdo->exec("INSERT INTO users (name, email) VALUES ('Visible Name', 'private@example.test')");
$userId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO feedback_messages (name, email, message) VALUES ('Legacy Guest', 'legacy@example.test', 'private legacy')");
$legacyId = (int) $pdo->lastInsertId();
bbs_assert_same('legacy', $pdo->query("SELECT status FROM feedback_messages WHERE id = {$legacyId}")->fetchColumn(), 'Existing-style anonymous rows must default to legacy.');

$publishedId = save_feedback_message([
    'user_id' => $userId,
    'message' => 'Published topic',
    'format' => 'markdown',
    'status' => 'published',
    'ip_address' => '203.0.113.10',
]);
$pendingId = save_feedback_message([
    'user_id' => $userId,
    'message' => 'Pending topic',
    'format' => 'markdown',
    'status' => 'pending',
    'ip_address' => '203.0.113.10',
]);

$guestRows = public_feedback_messages(20, 0, null);
bbs_assert_same(1, count($guestRows), 'Guests must only see published messages.');
bbs_assert_same('Visible Name', $guestRows[0]['public_name'] ?? null, 'Public rows must expose the account name.');
bbs_assert_same(false, array_key_exists('email', $guestRows[0]), 'Public rows must never include feedback email.');
bbs_assert_same(false, array_key_exists('user_email', $guestRows[0]), 'Public rows must never include account email.');
bbs_assert_same(1, public_feedback_messages_count(null), 'Guest count must exclude pending and legacy rows.');
bbs_assert_same(2, public_feedback_messages_count($userId), 'A user must see their own pending message in addition to public rows.');

$pdo->exec("INSERT INTO users (name, email) VALUES ('masked@example.test', 'masked@example.test')");
$maskedUserId = (int) $pdo->lastInsertId();
save_feedback_message(['user_id' => $maskedUserId, 'message' => 'Masked author', 'status' => 'published']);
$maskedRows = public_feedback_messages(20, 0, null);
$maskedRow = array_values(array_filter($maskedRows, static fn(array $row): bool => $row['message'] === 'Masked author'))[0] ?? [];
bbs_assert_same(null, $maskedRow['public_name'] ?? null, 'An email-shaped account name must be masked publicly.');

save_admin_feedback_reply($publishedId, 1, 'Official answer');
$answeredRows = public_feedback_messages(20, 0, null);
$answered = array_values(array_filter($answeredRows, static fn(array $row): bool => (int) $row['id'] === $publishedId))[0] ?? [];
bbs_assert_same('Official answer', $answered['admin_reply'] ?? null, 'Published rows must include the official admin reply.');

edit_feedback_message_by_admin($publishedId, 1, 'Corrected topic');
bbs_assert_same('Corrected topic', $pdo->query("SELECT message FROM feedback_messages WHERE id = {$publishedId}")->fetchColumn(), 'Admin edit must update the public message.');
bbs_assert_same(1, (int) $pdo->query("SELECT COUNT(*) FROM feedback_revisions WHERE feedback_id = {$publishedId}")->fetchColumn(), 'Admin edits must preserve one revision.');

soft_delete_feedback_message($publishedId);
bbs_assert_same(1, public_feedback_messages_count(null), 'Soft-deleted messages must disappear publicly without hiding other posts.');
restore_feedback_message($publishedId);
bbs_assert_same(2, public_feedback_messages_count(null), 'Restored published messages must return publicly.');

publish_feedback_message($pendingId, 1);
bbs_assert_same('published', $pdo->query("SELECT status FROM feedback_messages WHERE id = {$pendingId}")->fetchColumn(), 'Admin approval must publish a pending message.');

$duplicate = feedback_submission_violation($userId, '203.0.113.10', 'Pending   topic');
bbs_assert_same('duplicate', $duplicate, 'Normalized duplicate content must be rejected.');

$unsafe = feedback_render_markdown('<script>alert(1)</script> **safe** [bad](javascript:alert(1)) [good](https://example.com)');
bbs_assert_same(false, str_contains($unsafe, '<script'), 'Markdown rendering must escape raw HTML.');
bbs_assert_same(false, str_contains($unsafe, 'href="javascript:'), 'Markdown rendering must reject unsafe links.');
bbs_assert_true(str_contains($unsafe, '<strong>safe</strong>'), 'Safe Markdown emphasis should render.');
bbs_assert_true(str_contains($unsafe, 'rel="nofollow ugc noopener"'), 'Public links must use UGC nofollow protection.');

$audit = $pdo->prepare('INSERT INTO login_audit (email, provider, status, ip_address) VALUES (:email, "email", :status, :ip)');
for ($i = 0; $i < 10; $i++) {
    $audit->execute([':email' => 'target@example.test', ':status' => 'failed', ':ip' => '198.51.100.4']);
}
bbs_assert_same(true, login_attempt_rate_limited('target@example.test', '198.51.100.4'), 'Ten failed logins in 15 minutes must trigger throttling.');
for ($i = 0; $i < 5; $i++) {
    $audit->execute([':email' => 'new' . $i . '@example.test', ':status' => 'registered', ':ip' => '198.51.100.5']);
}
bbs_assert_same(true, registration_rate_limited('198.51.100.5'), 'Five registrations from one IP in an hour must trigger throttling.');

purge_feedback_message($publishedId);
bbs_assert_same(0, (int) $pdo->query("SELECT COUNT(*) FROM feedback_messages WHERE id = {$publishedId}")->fetchColumn(), 'Permanent deletion must remove the message.');

$indexSource = file_get_contents(dirname(__DIR__) . '/index.php');
$adminSource = file_get_contents(dirname(__DIR__) . '/admin/admin.php');
bbs_assert_true(is_string($indexSource) && str_contains($indexSource, 'class="button ghost feedback-open" href='), 'Feedback buttons must be links to the BBS page.');
bbs_assert_true(is_string($indexSource) && !str_contains($indexSource, 'data-feedback-open'), 'Feedback buttons must no longer open the old dialog.');
foreach (['feedback_approve', 'feedback_reply', 'feedback_edit', 'feedback_delete', 'feedback_restore'] as $mode) {
    bbs_assert_true(is_string($adminSource) && str_contains($adminSource, "value=\"{$mode}\""), "Admin BBS must expose {$mode}.");
}

fwrite(STDOUT, "feedback BBS tests passed\n");
