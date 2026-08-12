<?php
declare(strict_types=1);

define('SYNTREE_SKIP_VISITOR_LOG', true);
require __DIR__ . '/../src/bootstrap.php';

const ADMIN_TITLE = 'MerlinSyntaxStudio Admin';
const ADMIN_TIMEZONE = 'Asia/Shanghai';
const ADMIN_DEFAULT_PAGE_SIZE = 20;
const ADMIN_INSTITUTION_REFRESH_LIMIT = 5;

$mode = (string) ($_POST['mode'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'login') {
        admin_handle_login();
    }
    if ($mode === 'logout') {
        require_csrf();
        unset($_SESSION['standalone_admin_id']);
        flash('success', 'Signed out.');
        redirect('admin.php');
    }
    admin_require_login();
    require_csrf();

    if ($mode === 'reset_user_password') {
        admin_reset_user_password();
    }
    if ($mode === 'toggle_user_status') {
        admin_toggle_user_status();
    }
    if ($mode === 'change_admin_password') {
        admin_change_password();
    }
    if ($mode === 'update_languages') {
        admin_update_languages();
    }
    if ($mode === 'refresh_ip_enrichment') {
        admin_refresh_ip_enrichment();
    }
    if ($mode === 'feedback_approve') {
        admin_feedback_approve();
    }
    if ($mode === 'feedback_reply') {
        admin_feedback_reply();
    }
    if ($mode === 'feedback_delete_reply') {
        admin_feedback_delete_reply();
    }
    if ($mode === 'feedback_edit') {
        admin_feedback_edit();
    }
    if ($mode === 'feedback_delete') {
        admin_feedback_delete();
    }
    if ($mode === 'feedback_restore') {
        admin_feedback_restore();
    }
    if ($mode === 'feedback_purge') {
        admin_feedback_purge();
    }

    flash('error', 'Unknown admin action.');
    admin_redirect_tab('overview');
}

if (!admin_current()) {
    admin_render_login();
    exit;
}

if (($_GET['download'] ?? '') === 'visitor_ips') {
    admin_download_visitor_ips();
    exit;
}
if (($_GET['download'] ?? '') === 'institutions') {
    admin_download_institutions();
    exit;
}

admin_render_dashboard();

function admin_current(): ?array
{
    $id = $_SESSION['standalone_admin_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM admin_accounts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $id]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

function admin_require_login(): array
{
    $admin = admin_current();
    if (!$admin) {
        flash('error', 'Please sign in as administrator.');
        redirect('admin.php');
    }
    return $admin;
}

function admin_handle_login(): void
{
    require_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $admin = find_standalone_admin($username);

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        flash('error', 'Admin username or password is incorrect.');
        redirect('admin.php');
    }

    session_regenerate_id(true);
    $_SESSION['standalone_admin_id'] = (int) $admin['id'];
    flash('success', 'Signed in.');
    admin_redirect_tab('overview');
}

function admin_reset_user_password(): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');
    $user = $userId > 0 ? find_user_by_id($userId) : null;

    if (!$user) {
        flash('error', 'User not found.');
        admin_redirect_tab('users');
    }
    if (strlen($password) < 8) {
        flash('error', 'New password must be at least 8 characters.');
        admin_redirect_tab('users');
    }

    db()->prepare('UPDATE users SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute([
            ':hash' => password_hash($password, PASSWORD_DEFAULT),
            ':id' => $userId,
        ]);
    flash('success', 'Password reset for ' . $user['email'] . '.');
    admin_redirect_tab('users');
}

function admin_toggle_user_status(): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    $user = $userId > 0 ? find_user_by_id($userId) : null;
    if (!$user) {
        flash('error', 'User not found.');
        admin_redirect_tab('users');
    }

    db()->prepare('UPDATE users SET is_active = CASE is_active WHEN 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute([':id' => $userId]);
    flash('success', 'User status updated.');
    admin_redirect_tab('users');
}

function admin_change_password(): void
{
    $admin = admin_require_login();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');

    if (!password_verify($currentPassword, $admin['password_hash'])) {
        flash('error', 'Current admin password is incorrect.');
        admin_redirect_tab('security');
    }
    if (strlen($newPassword) < 10) {
        flash('error', 'New admin password must be at least 10 characters.');
        admin_redirect_tab('security');
    }

    db()->prepare('UPDATE admin_accounts SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute([
            ':hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => (int) $admin['id'],
        ]);
    flash('success', 'Admin password updated.');
    admin_redirect_tab('security');
}

function admin_update_languages(): void
{
    $enabled = $_POST['enabled_languages'] ?? [];
    if (!is_array($enabled)) {
        $enabled = [];
    }
    try {
        update_enabled_languages($enabled);
        flash('success', 'Language display settings updated.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    }
    admin_redirect_tab('languages');
}

function admin_refresh_ip_enrichment(): void
{
    $checked = refresh_unknown_ip_enrichments(ADMIN_INSTITUTION_REFRESH_LIMIT);
    if ($checked > 0) {
        flash('success', 'Institution data checked for ' . $checked . ' IP address' . ($checked === 1 ? '' : 'es') . '.');
    } else {
        flash('success', 'No unchecked IP addresses remain.');
    }
    admin_redirect_tab('institutions');
}

function admin_feedback_row_from_post(): array
{
    $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
    $row = $feedbackId > 0 ? feedback_message_by_id($feedbackId) : null;
    if (!$row) {
        flash('error', 'Feedback message not found.');
        admin_redirect_feedback();
    }
    return $row;
}

function admin_feedback_approve(): void
{
    $admin = admin_require_login();
    $row = admin_feedback_row_from_post();
    if (!empty($row['deleted_at'])) {
        flash('error', 'Restore this message before publishing it.');
        admin_redirect_feedback();
    }
    publish_feedback_message((int) $row['id'], (int) $admin['id']);
    flash('success', 'Feedback published.');
    admin_redirect_feedback('open');
}

function admin_feedback_reply(): void
{
    $admin = admin_require_login();
    $row = admin_feedback_row_from_post();
    $message = trim((string) ($_POST['reply'] ?? ''));
    if ($row['status'] !== 'published' || !empty($row['deleted_at'])) {
        flash('error', 'Publish and restore the message before replying.');
        admin_redirect_feedback();
    }
    if ($message === '' || mb_strlen($message) > 10000) {
        flash('error', 'Reply must contain between 1 and 10,000 characters.');
        admin_redirect_feedback();
    }
    save_admin_feedback_reply((int) $row['id'], (int) $admin['id'], $message);
    flash('success', 'Official reply saved.');
    admin_redirect_feedback('answered');
}

function admin_feedback_delete_reply(): void
{
    $row = admin_feedback_row_from_post();
    delete_admin_feedback_reply((int) $row['id']);
    flash('success', 'Official reply deleted.');
    admin_redirect_feedback('open');
}

function admin_feedback_edit(): void
{
    $admin = admin_require_login();
    $row = admin_feedback_row_from_post();
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($message === '' || mb_strlen($message) > 10000) {
        flash('error', 'Message must contain between 1 and 10,000 characters.');
        admin_redirect_feedback();
    }
    edit_feedback_message_by_admin((int) $row['id'], (int) $admin['id'], $message);
    flash('success', 'User message edited. The original version was preserved.');
    admin_redirect_feedback();
}

function admin_feedback_delete(): void
{
    $row = admin_feedback_row_from_post();
    soft_delete_feedback_message((int) $row['id']);
    flash('success', 'Feedback moved to the recycle bin.');
    admin_redirect_feedback('deleted');
}

function admin_feedback_restore(): void
{
    $row = admin_feedback_row_from_post();
    restore_feedback_message((int) $row['id']);
    flash('success', 'Feedback restored.');
    admin_redirect_feedback();
}

function admin_feedback_purge(): void
{
    $row = admin_feedback_row_from_post();
    if (empty($row['deleted_at'])) {
        flash('error', 'Move the message to the recycle bin before deleting it permanently.');
        admin_redirect_feedback();
    }
    admin_delete_feedback_attachment_file((string) ($row['attachment_path'] ?? ''));
    purge_feedback_message((int) $row['id']);
    flash('success', 'Feedback permanently deleted.');
    admin_redirect_feedback('deleted');
}

function admin_delete_feedback_attachment_file(string $path): void
{
    if (!preg_match('~^uploads/feedback/[a-zA-Z0-9._-]+$~', $path)) {
        return;
    }
    $target = dirname(__DIR__) . '/' . $path;
    if (is_file($target)) {
        @unlink($target);
    }
}

function admin_render_login(): void
{
    admin_header('Login');
    ?>
    <main class="admin-login">
        <section class="admin-card login-card">
            <p class="eyebrow">Private Admin</p>
            <h1><?= e(ADMIN_TITLE) ?></h1>
            <?php admin_flash(); ?>
            <form method="post" action="admin.php" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="mode" value="login">
                <label>
                    <span>Username</span>
                    <input name="username" autocomplete="username" value="admin" required>
                </label>
                <label>
                    <span>Password</span>
                    <input name="password" type="password" autocomplete="current-password" required>
                </label>
                <button type="submit">Sign in</button>
            </form>
        </section>
    </main>
    <?php
    admin_footer();
}

function admin_render_dashboard(): void
{
    $admin = admin_require_login();
    $tabs = admin_tabs();
    $activeTab = admin_active_tab($tabs);
    refresh_unknown_visitor_countries(100);
    $pageSize = admin_page_size();

    $users = [];
    $userPage = $userTotalPages = 1;
    if ($activeTab === 'users') {
        [$userPage, $userTotalPages, $userOffset] = admin_pagination_state(admin_user_count(), 'user_page', $pageSize);
        $users = admin_user_rows($pageSize, $userOffset);
    }

    $languageOptions = language_options();
    $enabledLanguages = enabled_language_codes();

    $countrySummary = [];
    $countryPage = $countryTotalPages = 1;
    if ($activeTab === 'countries') {
        [$countryPage, $countryTotalPages, $countryOffset] = admin_pagination_state(visitor_country_summary_count(), 'country_page', $pageSize);
        $countrySummary = visitor_country_summary(null, $pageSize, $countryOffset);
    }

    $feedbackMessages = [];
    $feedbackPage = $feedbackTotalPages = 1;
    $feedbackFilter = admin_feedback_filter();
    if ($activeTab === 'overview') {
        [$feedbackPage, $feedbackTotalPages, $feedbackOffset] = admin_pagination_state(feedback_messages_count($feedbackFilter), 'feedback_page', $pageSize);
        $feedbackMessages = recent_feedback_messages($pageSize, $feedbackOffset, $feedbackFilter);
    }

    $institutionFilter = admin_institution_filter();
    $institutionRows = [];
    $institutionPage = $institutionTotalPages = 1;
    if ($activeTab === 'institutions') {
        [$institutionPage, $institutionTotalPages, $institutionOffset] = admin_pagination_state(institution_summary_count($institutionFilter), 'institution_page', $pageSize);
        $institutionRows = institution_summary_rows($institutionFilter, $pageSize, $institutionOffset);
    }
    $institutionPending = $activeTab === 'institutions' ? ip_enrichment_pending_count() : 0;

    $visitors = [];
    $visitorPage = $visitorTotalPages = 1;
    if ($activeTab === 'visitors') {
        [$visitorPage, $visitorTotalPages, $visitorOffset] = admin_pagination_state(visitor_events_count(), 'visitor_page', $pageSize);
        $visitors = recent_visitor_events($pageSize, null, $visitorOffset);
    }

    $stats = [
        'visitor_events' => visitor_events_count(),
        'countries' => visitor_country_count(),
        'users' => admin_user_count(),
        'active_users' => (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
    ];

    admin_header('Dashboard');
    ?>
    <main class="admin-shell">
        <header class="admin-topbar">
            <div>
                <p class="eyebrow">Private Admin</p>
                <h1><?= e(ADMIN_TITLE) ?></h1>
                <p class="muted">Signed in as <?= e($admin['username']) ?>.</p>
            </div>
            <form method="post" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="mode" value="logout">
                <button type="submit" class="secondary">Sign out</button>
            </form>
        </header>

        <?php admin_flash(); ?>

        <section class="admin-stats">
            <div data-stat-icon="V"><span>Visitor records</span><strong><?= $stats['visitor_events'] ?></strong></div>
            <div data-stat-icon="C"><span>Countries</span><strong><?= $stats['countries'] ?></strong></div>
            <div data-stat-icon="U"><span>Registered users</span><strong><?= $stats['users'] ?></strong></div>
            <div data-stat-icon="A"><span>Active users</span><strong><?= $stats['active_users'] ?></strong></div>
        </section>

        <?php admin_render_tabs($tabs, $activeTab); ?>

        <?php if ($activeTab === 'users'): ?>
        <section class="admin-card">
            <div class="section-heading">
                <h2>Registered Users</h2>
                <?php admin_page_size_selector(); ?>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Login</th>
                        <th>Saved Trees</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= (int) $user['id'] ?></td>
                            <td><?= e($user['name']) ?></td>
                            <td><?= e($user['email']) ?></td>
                            <td><?= e(admin_provider_label($user)) ?></td>
                            <td><?= (int) $user['saved_trees'] ?></td>
                            <td><?= (int) $user['is_active'] === 1 ? 'active' : 'disabled' ?></td>
                            <td><?= e(admin_time($user['created_at'])) ?></td>
                            <td class="admin-actions">
                                <form method="post" action="admin.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="return_tab" value="users">
                                    <input type="hidden" name="mode" value="toggle_user_status">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <button type="submit" class="small-button"><?= (int) $user['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
                                </form>
                                <form method="post" action="admin.php" class="reset-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="return_tab" value="users">
                                    <input type="hidden" name="mode" value="reset_user_password">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <input name="password" type="password" minlength="8" placeholder="New password" required>
                                    <button type="submit" class="small-button">Reset</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php admin_pagination($userPage, $userTotalPages, 'user_page'); ?>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'countries'): ?>
        <section class="admin-card">
            <div class="section-heading">
                <h2>Visitor Countries</h2>
                <?php admin_page_size_selector(); ?>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Country</th><th>Code</th><th>Visitors</th><th>Records</th><th>Latest</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($countrySummary as $row): ?>
                        <tr>
                            <td><?= admin_country_label($row) ?></td>
                            <td><?= e($row['country_code']) ?></td>
                            <td><?= (int) $row['visitors'] ?></td>
                            <td><?= (int) $row['visits'] ?></td>
                            <td><?= e(admin_time($row['latest_visit'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php admin_pagination($countryPage, $countryTotalPages, 'country_page'); ?>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'institutions'): ?>
        <section class="admin-card">
            <div class="section-heading">
                <div>
                    <h2>Institutions</h2>
                    <p class="muted">Based on existing visitor records. Old visitor records are not changed.</p>
                </div>
                <div class="admin-actions">
                    <?php admin_page_size_selector(); ?>
                    <form method="post" action="admin.php?tab=institutions">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="mode" value="refresh_ip_enrichment">
                        <button type="submit" class="small-button">Check next <?= ADMIN_INSTITUTION_REFRESH_LIMIT ?> IPs</button>
                    </form>
                    <a class="small-button" href="<?= e(admin_institution_download_url()) ?>">Download CSV</a>
                </div>
            </div>
            <div class="filter-pills">
                <a class="<?= $institutionFilter === 'all' ? 'active' : '' ?>" href="<?= e(admin_institution_filter_url('all')) ?>">All networks</a>
                <a class="<?= $institutionFilter === 'academic' ? 'active' : '' ?>" href="<?= e(admin_institution_filter_url('academic')) ?>">Academic only</a>
                <a class="<?= $institutionFilter === 'hide_networks' ? 'active' : '' ?>" href="<?= e(admin_institution_filter_url('hide_networks')) ?>">Hide cloud / ISP / bots</a>
            </div>
            <p class="muted">Unchecked IP addresses remaining: <?= (int) $institutionPending ?>. Confidence is an estimate from ASN, network organization, ISP names, and any available RDAP or reverse DNS data.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Institution / network</th>
                        <th>Type</th>
                        <th>Confidence</th>
                        <th>Country</th>
                        <th>ASN</th>
                        <th>Network org</th>
                        <th>IPs</th>
                        <th>Sessions</th>
                        <th>Records</th>
                        <th>First</th>
                        <th>Latest</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$institutionRows): ?>
                        <tr><td colspan="11">No institution data for this filter yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($institutionRows as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e($row['institution_guess']) ?></strong>
                                <?php if (!empty($row['rdns'])): ?>
                                    <br><small class="muted"><?= e($row['rdns']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= admin_institution_type_label($row) ?></td>
                            <td><?= (int) $row['confidence'] ?></td>
                            <td><?= admin_country_label($row) ?></td>
                            <td><?= e($row['asn'] ?? '') ?></td>
                            <td><?= e($row['network_org'] ?? '') ?></td>
                            <td><?= (int) $row['ip_count'] ?></td>
                            <td><?= (int) $row['sessions'] ?></td>
                            <td><?= (int) $row['visits'] ?></td>
                            <td><?= e(admin_time($row['first_visit'])) ?></td>
                            <td><?= e(admin_time($row['latest_visit'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php admin_pagination($institutionPage, $institutionTotalPages, 'institution_page'); ?>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'languages'): ?>
        <section class="admin-card">
            <h2>Language Display</h2>
            <form method="post" action="admin.php" class="admin-form compact">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="return_tab" value="languages">
                <input type="hidden" name="mode" value="update_languages">
                <div class="language-toggle-list">
                    <?php foreach ($languageOptions as $code => $label): ?>
                        <label class="language-toggle">
                            <span><?= e($label) ?></span>
                            <input type="checkbox" name="enabled_languages[]" value="<?= e($code) ?>" <?= in_array($code, $enabledLanguages, true) ? 'checked' : '' ?>>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="muted">Unchecked languages disappear from the public language selector. At least one language must remain enabled.</p>
                <button type="submit">Save language settings</button>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'security'): ?>
        <section class="admin-card">
            <h2>Admin Password</h2>
            <form method="post" action="admin.php" class="admin-form compact">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="return_tab" value="security">
                <input type="hidden" name="mode" value="change_admin_password">
                <label>
                    <span>Current password</span>
                    <input name="current_password" type="password" autocomplete="current-password" required>
                </label>
                <label>
                    <span>New password</span>
                    <input name="new_password" type="password" autocomplete="new-password" minlength="10" required>
                </label>
                <button type="submit">Change admin password</button>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'overview'): ?>
        <section class="admin-card">
            <div class="section-heading">
                <div>
                    <h2>BBS Management</h2>
                    <p class="muted">Only published messages appear publicly. Email addresses remain private in this administrator view.</p>
                </div>
                <?php admin_page_size_selector(); ?>
            </div>
            <nav class="filter-pills feedback-filter-pills" aria-label="BBS status filters">
                <?php foreach (admin_feedback_filters() as $filter => $label): ?>
                    <a class="<?= $feedbackFilter === $filter ? 'active' : '' ?>" href="<?= e(admin_feedback_filter_url($filter)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if (!$feedbackMessages): ?>
                <div class="admin-feedback-empty">No feedback messages match this filter.</div>
            <?php endif; ?>

            <div class="admin-feedback-list">
                <?php foreach ($feedbackMessages as $row): ?>
                    <?php
                    $isDeleted = !empty($row['deleted_at']);
                    $isLegacy = ($row['status'] ?? 'legacy') === 'legacy';
                    $isPending = ($row['status'] ?? '') === 'pending';
                    $isPublished = ($row['status'] ?? '') === 'published';
                    $displayName = $row['user_name'] ?: ($row['name'] ?: 'Legacy guest');
                    $displayEmail = $row['user_email'] ?: ($row['email'] ?: '');
                    ?>
                    <article class="admin-feedback-item <?= $isDeleted ? 'is-deleted' : '' ?>">
                        <header class="admin-feedback-header">
                            <div>
                                <strong><?= e($displayName) ?></strong>
                                <?php if ($displayEmail !== ''): ?>
                                    <span><?= e($displayEmail) ?></span>
                                <?php endif; ?>
                                <time datetime="<?= e((string) $row['created_at']) ?>"><?= e(admin_time($row['created_at'])) ?></time>
                            </div>
                            <div class="admin-feedback-badges">
                                <?php if ($isDeleted): ?><span class="status-badge status-deleted">recycle bin</span><?php endif; ?>
                                <?php if ($isLegacy): ?><span class="status-badge status-legacy">legacy private</span><?php endif; ?>
                                <?php if ($isPending): ?><span class="status-badge status-pending">pending</span><?php endif; ?>
                                <?php if ($isPublished): ?><span class="status-badge status-published">published</span><?php endif; ?>
                                <?php if (!empty($row['admin_reply'])): ?><span class="status-badge status-answered">answered</span><?php endif; ?>
                            </div>
                        </header>

                        <?php if (!$isDeleted): ?>
                            <form method="post" action="admin.php" class="admin-feedback-editor">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="mode" value="feedback_edit">
                                <input type="hidden" name="feedback_id" value="<?= (int) $row['id'] ?>">
                                <textarea name="message" rows="5" maxlength="10000" aria-label="Edit user feedback" required><?= e(admin_feedback_message_text($row)) ?></textarea>
                                <button type="submit" class="small-button">Save user-message edit</button>
                            </form>
                        <?php else: ?>
                            <div class="admin-feedback-deleted-copy"><?= nl2br(e(admin_feedback_message_text($row))) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($row['edited_at'])): ?>
                            <p class="muted">Administrator edited <?= e(admin_time($row['edited_at'])) ?>. Original versions are preserved.</p>
                        <?php endif; ?>

                        <?php if (!empty($row['attachment_path'])): ?>
                            <p><a class="text-link" href="<?= e(admin_feedback_attachment_href((string) $row['attachment_path'])) ?>" target="_blank" rel="noopener noreferrer">View attachment: <?= e($row['attachment_name'] ?: 'image') ?></a></p>
                        <?php endif; ?>

                        <?php if (!$isDeleted && $isPending): ?>
                            <form method="post" action="admin.php" class="admin-feedback-action">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="mode" value="feedback_approve">
                                <input type="hidden" name="feedback_id" value="<?= (int) $row['id'] ?>">
                                <button type="submit">Approve and publish</button>
                            </form>
                        <?php endif; ?>

                        <?php if (!$isDeleted && $isPublished): ?>
                            <form method="post" action="admin.php" class="admin-feedback-reply">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="mode" value="feedback_reply">
                                <input type="hidden" name="feedback_id" value="<?= (int) $row['id'] ?>">
                                <label>
                                    <span>Official administrator reply</span>
                                    <textarea name="reply" rows="4" maxlength="10000" required><?= e((string) ($row['admin_reply'] ?? '')) ?></textarea>
                                </label>
                                <div class="admin-actions">
                                    <button type="submit"><?= empty($row['admin_reply']) ? 'Publish reply' : 'Update reply' ?></button>
                                </div>
                            </form>
                            <?php if (!empty($row['admin_reply'])): ?>
                                <form method="post" action="admin.php" class="admin-feedback-action" onsubmit="return confirm('Delete this official reply?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="mode" value="feedback_delete_reply">
                                    <input type="hidden" name="feedback_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="small-button danger">Delete reply</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>

                        <footer class="admin-feedback-footer">
                            <?php if (!$isDeleted): ?>
                                <form method="post" action="admin.php" onsubmit="return confirm('Move this feedback message to the recycle bin?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="mode" value="feedback_delete">
                                    <input type="hidden" name="feedback_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="small-button danger">Move to recycle bin</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="admin.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="mode" value="feedback_restore">
                                    <input type="hidden" name="feedback_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="small-button">Restore</button>
                                </form>
                                <form method="post" action="admin.php" onsubmit="return confirm('Permanently delete this message and its attachment? This cannot be undone.')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="mode" value="feedback_purge">
                                    <input type="hidden" name="feedback_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="small-button danger">Delete permanently</button>
                                </form>
                            <?php endif; ?>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php admin_pagination($feedbackPage, $feedbackTotalPages, 'feedback_page'); ?>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'visitors'): ?>
        <section class="admin-card">
            <div class="section-heading">
                <div>
                    <h2>Visitor Records</h2>
                    <p class="muted">Showing all stored visitor records in China time.</p>
                </div>
                <div class="admin-actions">
                    <?php admin_page_size_selector(); ?>
                    <a class="small-button" href="admin.php?tab=visitors&amp;download=visitor_ips">Download all IP records</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Time</th><th>Country</th><th>IP</th><th>User</th><th>Path</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visitors as $row): ?>
                        <tr>
                            <td><?= e(admin_time($row['created_at'])) ?></td>
                            <td><?= admin_country_label($row) ?></td>
                            <td><?= e($row['ip_address']) ?></td>
                            <td><?= e($row['user_email'] ?: 'guest') ?></td>
                            <td><?= e($row['path']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php admin_pagination($visitorPage, $visitorTotalPages, 'visitor_page'); ?>
        </section>
        <?php endif; ?>
    </main>
    <?php
    admin_footer();
}

function admin_tabs(): array
{
    return [
        'overview' => 'Overview',
        'users' => 'Users',
        'countries' => 'Visitor Countries',
        'institutions' => 'Institutions',
        'languages' => 'Language Display',
        'security' => 'Admin Password',
        'visitors' => 'Visitor IP Records',
    ];
}

function admin_active_tab(array $tabs): string
{
    $tab = (string) ($_GET['tab'] ?? 'overview');
    if ($tab === 'feedback') {
        return 'overview';
    }
    return array_key_exists($tab, $tabs) ? $tab : 'overview';
}

function admin_render_tabs(array $tabs, string $activeTab): void
{
    ?>
    <nav class="admin-tabs" aria-label="Admin sections">
        <?php foreach ($tabs as $tab => $label): ?>
            <a class="<?= $tab === $activeTab ? 'active' : '' ?>" href="<?= e(admin_tab_url($tab)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function admin_tab_url(string $tab): string
{
    return 'admin.php?' . http_build_query([
        'tab' => $tab,
        'per_page' => admin_page_size(),
    ]);
}

function admin_redirect_tab(string $tab): never
{
    redirect(admin_tab_url($tab));
}

function admin_feedback_filters(): array
{
    return [
        'all' => 'All active',
        'pending' => 'Pending review',
        'open' => 'Published · no reply',
        'answered' => 'Answered',
        'legacy' => 'Legacy private',
        'deleted' => 'Recycle bin',
    ];
}

function admin_feedback_filter(): string
{
    $filter = (string) ($_GET['feedback_status'] ?? 'all');
    return array_key_exists($filter, admin_feedback_filters()) ? $filter : 'all';
}

function admin_feedback_filter_url(string $filter): string
{
    if (!array_key_exists($filter, admin_feedback_filters())) {
        $filter = 'all';
    }
    return 'admin.php?' . http_build_query([
        'tab' => 'overview',
        'feedback_status' => $filter,
        'per_page' => admin_page_size(),
    ]);
}

function admin_redirect_feedback(?string $filter = null): never
{
    redirect(admin_feedback_filter_url($filter ?? 'all'));
}

function admin_provider_label(array $user): string
{
    $providers = array_filter(array_map('trim', explode(',', (string) ($user['providers'] ?? ''))));
    if (!empty($user['password_hash'])) {
        array_unshift($providers, 'email');
    }
    return implode(', ', array_unique($providers));
}

function admin_page_size(): int
{
    $size = (int) ($_GET['per_page'] ?? ADMIN_DEFAULT_PAGE_SIZE);
    return in_array($size, [20, 40, 100], true) ? $size : ADMIN_DEFAULT_PAGE_SIZE;
}

function admin_pagination_state(int $total, string $pageParam, int $pageSize): array
{
    $totalPages = max(1, (int) ceil($total / $pageSize));
    $page = min(max(1, (int) ($_GET[$pageParam] ?? 1)), $totalPages);
    return [$page, $totalPages, ($page - 1) * $pageSize];
}

function admin_page_size_selector(): void
{
    $query = [];
    foreach (['tab', 'institution_filter', 'feedback_status'] as $key) {
        if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
            $query[$key] = (string) $_GET[$key];
        }
    }
    ?>
    <form method="get" action="admin.php" class="page-size-form">
        <?php foreach ($query as $key => $value): ?>
            <?php if (is_scalar($value)): ?>
                <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <label>
            <span>Rows per page</span>
            <select name="per_page" onchange="this.form.submit()">
                <?php foreach ([20, 40, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= admin_page_size() === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <?php
}

function admin_time(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(ADMIN_TIMEZONE))
            ->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return $value;
    }
}

function admin_download_visitor_ips(): void
{
    admin_require_login();
    $filename = 'visitor-ip-records-' . (new DateTimeImmutable('now', new DateTimeZone(ADMIN_TIMEZONE)))->format('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['time_china', 'time_utc', 'country_code', 'country_name', 'ip_address', 'user_email', 'path', 'user_agent', 'session_key']);
    foreach (all_visitor_events() as $row) {
        fputcsv($out, [
            admin_time($row['created_at'] ?? ''),
            $row['created_at'] ?? '',
            $row['country_code'] ?? '',
            $row['country_name'] ?? '',
            $row['ip_address'] ?? '',
            $row['user_email'] ?: 'guest',
            $row['path'] ?? '',
            $row['user_agent'] ?? '',
            $row['session_key'] ?? '',
        ]);
    }
    fclose($out);
}

function admin_download_institutions(): void
{
    admin_require_login();
    $filter = admin_institution_filter();
    $filename = 'institution-summary-' . $filter . '-' . (new DateTimeImmutable('now', new DateTimeZone(ADMIN_TIMEZONE)))->format('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'institution_guess',
        'institution_type',
        'confidence',
        'country_code',
        'country_name',
        'asn',
        'network_org',
        'rdns',
        'ip_count',
        'sessions',
        'visitor_records',
        'first_visit_china',
        'latest_visit_china',
        'last_checked_utc',
    ]);
    foreach (institution_summary_rows($filter, 5000) as $row) {
        fputcsv($out, [
            $row['institution_guess'] ?? '',
            $row['institution_type'] ?? '',
            $row['confidence'] ?? '',
            $row['country_code'] ?? '',
            $row['country_name'] ?? '',
            $row['asn'] ?? '',
            $row['network_org'] ?? '',
            $row['rdns'] ?? '',
            $row['ip_count'] ?? '',
            $row['sessions'] ?? '',
            $row['visits'] ?? '',
            admin_time($row['first_visit'] ?? ''),
            admin_time($row['latest_visit'] ?? ''),
            $row['last_checked'] ?? '',
        ]);
    }
    fclose($out);
}

function admin_pagination(int $page, int $totalPages, string $param): void
{
    $prev = max(1, $page - 1);
    $next = min($totalPages, $page + 1);
    ?>
    <nav class="pagination" aria-label="Table pagination">
        <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e(admin_page_url($param, $prev)) ?>">Previous</a>
        <span>Page <?= $page ?> / <?= $totalPages ?></span>
        <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= e(admin_page_url($param, $next)) ?>">Next</a>
    </nav>
    <?php
}

function admin_page_url(string $param, int $page): string
{
    $query = $_GET;
    unset($query['download']);
    $query[$param] = $page;
    return 'admin.php' . ($query ? '?' . http_build_query($query) : '');
}

function admin_institution_filter(): string
{
    $filter = (string) ($_GET['institution_filter'] ?? 'all');
    return in_array($filter, ['all', 'academic', 'hide_networks'], true) ? $filter : 'all';
}

function admin_institution_filter_url(string $filter): string
{
    return 'admin.php?' . http_build_query([
        'tab' => 'institutions',
        'institution_filter' => $filter,
        'per_page' => admin_page_size(),
    ]);
}

function admin_institution_download_url(): string
{
    return 'admin.php?' . http_build_query([
        'tab' => 'institutions',
        'institution_filter' => admin_institution_filter(),
        'download' => 'institutions',
    ]);
}

function admin_institution_type_label(array $row): string
{
    $type = trim((string) ($row['institution_type'] ?? 'unknown'));
    if ($type === '') {
        $type = 'unknown';
    }
    $classes = ['type-badge', 'type-' . preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($type))];
    return '<span class="' . e(implode(' ', $classes)) . '">' . e($type) . '</span>';
}

function admin_country_label(array $row): string
{
    $code = strtoupper(trim((string) ($row['country_code'] ?? '')));
    $name = trim((string) ($row['country_name'] ?? 'Unknown'));
    if ($name === '') {
        $name = 'Unknown';
    }

    $flag = country_flag_html($code);
    $label = $name;
    if ($code !== '' && $code !== 'UNK' && $code !== 'LOCAL' && stripos($name, $code) === false) {
        $label .= ' ' . $code;
    }

    return '<span class="country-label">' . ($flag !== '' ? '<span class="country-flag">' . $flag . '</span>' : '') . e($label) . '</span>';
}

function country_flag_html(string $code): string
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[A-Z]{2}$/', $code)) {
        return '';
    }

    $flag = '';
    for ($i = 0; $i < 2; $i++) {
        $flag .= '&#' . (127462 + ord($code[$i]) - ord('A')) . ';';
    }
    return $flag;
}

function admin_feedback_attachment_href(string $path): string
{
    return is_dir(__DIR__ . '/src') ? $path : '../' . ltrim($path, '/');
}

function admin_feedback_message_text(array $row): string
{
    $message = (string) ($row['message'] ?? '');
    if (($row['format'] ?? '') === 'html') {
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = preg_replace('/<br\s*\/?>/i', "\n", $message) ?? $message;
        $message = preg_replace('/<\/p\s*>/i', "\n\n", $message) ?? $message;
        $message = strip_tags($message);
    }
    $message = str_replace(["\r\n", "\r"], "\n", $message);
    $message = preg_replace("/\n{3,}/", "\n\n", $message) ?? $message;
    return trim($message);
}

function admin_flash(): void
{
    foreach (consume_flash() as $message) {
        $type = $message['type'] === 'success' ? 'success' : 'error';
        echo '<div class="flash ' . e($type) . '">' . e($message['message']) . '</div>';
    }
}

function admin_header(string $title): void
{
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title><?= e($title) ?> · <?= e(ADMIN_TITLE) ?></title>
        <link rel="stylesheet" href="../style.css?v=<?= (int) @filemtime(__DIR__ . '/../style.css') ?>">
        <style>
            body { background: #f4f6f3; }
            .admin-login { display: grid; min-height: 100vh; place-items: center; padding: 24px; }
            .admin-shell { display: grid; gap: 16px; max-width: 1500px; margin: 0 auto; padding: 20px; }
            .admin-topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
            .admin-topbar h1, .login-card h1 { margin: 4px 0; font-size: 30px; line-height: 1.1; }
            .admin-card, .admin-stats > div {
                border: 1px solid #d5dee9;
                border-radius: 8px;
                background: #fff;
            }
            .admin-card { padding: 18px; }
            .login-card { width: min(100%, 420px); }
            .admin-card h2 { margin: 0 0 14px; font-size: 20px; }
            .section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 14px; }
            .section-heading h2 { margin-bottom: 4px; }
            .admin-form { display: grid; gap: 12px; }
            .admin-form label { display: grid; gap: 6px; color: #334155; font-size: 13px; font-weight: 800; }
            .admin-form input, .reset-inline input {
                min-height: 38px;
                border: 1px solid #cbd5e1;
                border-radius: 7px;
                padding: 0 10px;
                font: inherit;
            }
            .admin-form button, .secondary, .small-button {
                min-height: 38px;
                border: 1px solid #cbd5e1;
                border-radius: 7px;
                padding: 0 12px;
                background: #172033;
                color: #fff;
                font-weight: 800;
                cursor: pointer;
            }
            .secondary, .small-button { background: #fff; color: #172033; }
            .admin-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
            .admin-stats > div { display: grid; gap: 6px; padding: 16px; position: relative; overflow: hidden; }
            .admin-stats > div::after {
                content: attr(data-stat-icon);
                position: absolute;
                top: 14px;
                right: 16px;
                display: grid;
                place-items: center;
                width: 32px;
                height: 32px;
                border-radius: 999px;
                background: #edf4ff;
                color: #1d4ed8;
                font-size: 13px;
                font-weight: 900;
            }
            .admin-stats span { color: #64748b; font-size: 13px; font-weight: 700; }
            .admin-stats strong { color: #111827; font-size: 30px; }
            .admin-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding: 0 2px 2px;
                border-bottom: 1px solid #d5dee9;
            }
            .admin-tabs a {
                min-height: 40px;
                display: inline-flex;
                align-items: center;
                border: 1px solid #cbd5e1;
                border-bottom-color: #d5dee9;
                border-radius: 8px 8px 0 0;
                padding: 0 14px;
                color: #172033;
                background: #fff;
                font-size: 13px;
                font-weight: 850;
                text-decoration: none;
            }
            .admin-tabs a.active {
                border-color: #172033;
                background: #172033;
                color: #fff;
            }
            .filter-pills { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 12px; }
            .filter-pills a {
                min-height: 32px;
                display: inline-flex;
                align-items: center;
                border: 1px solid #cbd5e1;
                border-radius: 999px;
                padding: 0 12px;
                color: #172033;
                background: #fff;
                font-size: 12px;
                font-weight: 850;
                text-decoration: none;
            }
            .filter-pills a.active { border-color: #172033; background: #172033; color: #fff; }
            .type-badge {
                display: inline-flex;
                align-items: center;
                min-height: 24px;
                border-radius: 999px;
                padding: 0 9px;
                background: #f1f5f9;
                color: #334155;
                font-size: 12px;
                font-weight: 850;
            }
            .type-academic, .type-research { background: #e7f8ef; color: #166534; }
            .type-cloud, .type-isp, .type-proxy { background: #fff7ed; color: #9a3412; }
            .type-private, .type-unchecked, .type-unknown { background: #f1f5f9; color: #475569; }
            .country-label { display: inline-flex; align-items: center; gap: 8px; }
            .country-flag { font-size: 20px; line-height: 1; }
            .table-wrap { overflow: auto; }
            table { width: 100%; border-collapse: collapse; min-width: 760px; }
            th, td { border-bottom: 1px solid #e2e8f0; padding: 10px; text-align: left; vertical-align: top; }
            th { color: #334155; background: #f8fafc; font-size: 12px; text-transform: uppercase; }
            td { color: #1f2937; font-size: 13px; }
            .feedback-message-cell {
                min-width: 560px;
                max-width: none;
                white-space: normal;
                overflow-wrap: anywhere;
                line-height: 1.55;
            }
            .feedback-attachment-cell {
                max-width: 280px;
                overflow-wrap: anywhere;
            }
            .admin-feedback-list { display: grid; gap: 14px; }
            .admin-feedback-item {
                display: grid;
                gap: 12px;
                border: 1px solid #d8e0ea;
                border-radius: 8px;
                padding: 16px;
                background: #f8fafc;
            }
            .admin-feedback-item.is-deleted { border-style: dashed; background: #fff7f7; }
            .admin-feedback-header,
            .admin-feedback-footer {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
            }
            .admin-feedback-header > div:first-child { display: grid; gap: 3px; }
            .admin-feedback-header span,
            .admin-feedback-header time { color: #64748b; font-size: 12px; }
            .admin-feedback-badges { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }
            .status-badge {
                display: inline-flex;
                align-items: center;
                min-height: 25px;
                border-radius: 999px;
                padding: 0 9px;
                color: #334155;
                background: #e2e8f0;
                font-size: 11px;
                font-weight: 850;
            }
            .status-pending { color: #854d0e; background: #fef3c7; }
            .status-published, .status-answered { color: #166534; background: #dcfce7; }
            .status-deleted { color: #991b1b; background: #fee2e2; }
            .status-legacy { color: #475569; background: #e2e8f0; }
            .admin-feedback-editor,
            .admin-feedback-reply { display: grid; gap: 8px; }
            .admin-feedback-reply {
                border-left: 3px solid #1f8a70;
                padding: 12px 14px;
                background: #eefaf4;
            }
            .admin-feedback-reply label { display: grid; gap: 6px; color: #334155; font-size: 12px; font-weight: 800; }
            .admin-feedback-editor textarea,
            .admin-feedback-reply textarea {
                width: 100%;
                min-height: 110px;
                resize: vertical;
                border: 1px solid #cbd5e1;
                border-radius: 7px;
                padding: 10px;
                color: #1f2937;
                background: #fff;
                font: inherit;
                line-height: 1.55;
            }
            .admin-feedback-action { display: flex; justify-content: flex-start; }
            .admin-feedback-footer { justify-content: flex-end; border-top: 1px solid #e2e8f0; padding-top: 12px; }
            .admin-feedback-footer form { margin: 0; }
            .admin-feedback-deleted-copy { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.55; }
            .admin-feedback-empty { border: 1px dashed #cbd5e1; border-radius: 8px; padding: 30px 18px; color: #64748b; text-align: center; }
            .small-button.danger { border-color: #fecaca; color: #991b1b; background: #fff; }
            .admin-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
            .page-size-form label { display: flex; align-items: center; gap: 8px; color: #475569; font-size: 12px; font-weight: 800; }
            .page-size-form select {
                min-height: 38px;
                border: 1px solid #cbd5e1;
                border-radius: 7px;
                padding: 0 28px 0 10px;
                background: #fff;
                color: #172033;
                font: inherit;
            }
            .reset-inline { display: inline-flex; gap: 8px; }
            .reset-inline input { width: 150px; }
            .language-toggle-list { display: grid; gap: 10px; }
            .language-toggle {
                display: flex !important;
                grid-template-columns: none !important;
                align-items: center;
                justify-content: space-between;
                min-height: 38px;
                border: 1px solid #e2e8f0;
                border-radius: 7px;
                padding: 0 10px;
                background: #f8fafc;
            }
            .language-toggle input { width: 20px; min-height: 20px; accent-color: #172033; }
            .pagination { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding-top: 14px; color: #475569; font-size: 13px; font-weight: 800; }
            .pagination a {
                min-height: 34px;
                display: inline-flex;
                align-items: center;
                border: 1px solid #cbd5e1;
                border-radius: 7px;
                padding: 0 10px;
                color: #172033;
                background: #fff;
                text-decoration: none;
            }
            .pagination a.disabled { color: #94a3b8; background: #f8fafc; pointer-events: none; }
            .flash { max-width: none; margin: 0; }
            @media (max-width: 900px) {
                .admin-topbar { flex-direction: column; align-items: flex-start; }
                .admin-stats { grid-template-columns: 1fr 1fr; }
                .section-heading { flex-direction: column; }
                .admin-feedback-editor .small-button { width: 100%; }
            }
        </style>
    </head>
    <body>
    <?php
}

function admin_footer(): void
{
    ?>
    </body>
    </html>
    <?php
}
