<?php
declare(strict_types=1);

function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }
    $user = find_user_by_id((int) $id);
    if (!$user || (int) $user['is_active'] !== 1) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('index.php?action=login');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function handle_email_login(): void
{
    require_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = $email !== '' ? find_user_by_email($email) : null;

    if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($user ? (int) $user['id'] : null, $email, 'email', 'failed');
        flash('error', 'Email or password is incorrect.');
        redirect('index.php?action=login');
    }

    if ((int) $user['is_active'] !== 1) {
        record_login_attempt((int) $user['id'], $email, 'email', 'blocked');
        flash('error', 'This account has been disabled.');
        redirect('index.php?action=login');
    }

    record_login_attempt((int) $user['id'], $email, 'email', 'success');
    login_user($user);
    redirect('index.php?action=workspace');
}

function handle_register(): void
{
    require_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        flash('error', 'Use a valid name, email, and a password of at least 8 characters.');
        redirect('index.php?action=register');
    }

    if (find_user_by_email($email)) {
        flash('error', 'An account with this email already exists.');
        redirect('index.php?action=register');
    }

    $id = create_email_user($name, $email, $password);
    $user = find_user_by_id($id);
    if (!$user) {
        flash('error', 'Registration failed.');
        redirect('index.php?action=register');
    }

    record_login_attempt($id, $email, 'email', 'registered');
    login_user($user);
    redirect('index.php?action=workspace');
}
