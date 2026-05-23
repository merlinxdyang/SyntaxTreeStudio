<?php
declare(strict_types=1);

function oauth_provider_config(string $provider): ?array
{
    $config = OAUTH_PROVIDERS[$provider] ?? null;
    if (!$config) {
        return null;
    }
    $clientId = getenv($config['client_id_env']);
    $clientSecret = getenv($config['client_secret_env']);
    if (!is_string($clientId) || $clientId === '' || !is_string($clientSecret) || $clientSecret === '') {
        return $config + ['configured' => false, 'client_id' => '', 'client_secret' => ''];
    }
    return $config + ['configured' => true, 'client_id' => $clientId, 'client_secret' => $clientSecret];
}

function oauth_redirect_uri(string $provider): string
{
    return app_base_url() . '/index.php?action=oauth_callback&provider=' . rawurlencode($provider);
}

function oauth_start(string $provider): void
{
    $config = oauth_provider_config($provider);
    if (!$config || !$config['configured']) {
        flash('error', 'OAuth for this provider is not configured.');
        redirect('index.php?action=login');
    }

    $state = bin2hex(random_bytes(24));
    $_SESSION['oauth_state_' . $provider] = $state;
    $query = http_build_query([
        'client_id' => $config['client_id'],
        'redirect_uri' => oauth_redirect_uri($provider),
        'response_type' => 'code',
        'scope' => $config['scope'],
        'state' => $state,
    ]);
    redirect($config['auth_url'] . '?' . $query);
}

function oauth_callback(string $provider): void
{
    $config = oauth_provider_config($provider);
    if (!$config || !$config['configured']) {
        flash('error', 'OAuth for this provider is not configured.');
        redirect('index.php?action=login');
    }

    $expected = $_SESSION['oauth_state_' . $provider] ?? '';
    unset($_SESSION['oauth_state_' . $provider]);
    $state = (string) ($_GET['state'] ?? '');
    $code = (string) ($_GET['code'] ?? '');
    if ($code === '' || $expected === '' || !hash_equals((string) $expected, $state)) {
        flash('error', 'OAuth state verification failed.');
        redirect('index.php?action=login');
    }

    $token = oauth_post_token($config['token_url'], [
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => oauth_redirect_uri($provider),
    ]);
    $accessToken = $token['access_token'] ?? '';
    if (!is_string($accessToken) || $accessToken === '') {
        flash('error', 'OAuth token exchange failed.');
        redirect('index.php?action=login');
    }

    $profile = oauth_get_profile($provider, $config, $accessToken);
    $user = upsert_oauth_user($provider, $profile);
    if ((int) $user['is_active'] !== 1) {
        record_login_attempt((int) $user['id'], $profile['email'], $provider, 'blocked');
        flash('error', 'This account has been disabled.');
        redirect('index.php?action=login');
    }

    record_login_attempt((int) $user['id'], $profile['email'], $provider, 'success');
    login_user($user);
    redirect('index.php?action=workspace');
}

function oauth_post_token(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        return [];
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

function oauth_get_json(string $url, string $accessToken): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: Syntree',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        return [];
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

function oauth_get_profile(string $provider, array $config, string $accessToken): array
{
    $raw = oauth_get_json($config['userinfo_url'], $accessToken);
    if ($provider === 'google') {
        $email = !empty($raw['email_verified']) ? strtolower((string) ($raw['email'] ?? '')) : '';
        return [
            'provider_user_id' => (string) ($raw['sub'] ?? ''),
            'email' => $email,
            'name' => (string) ($raw['name'] ?? $raw['email'] ?? 'Google User'),
        ];
    }

    $email = strtolower((string) ($raw['email'] ?? ''));
    if ($email === '' && isset($config['email_url'])) {
        $emails = oauth_get_json($config['email_url'], $accessToken);
        foreach ($emails as $candidate) {
            if (!empty($candidate['primary']) && !empty($candidate['verified']) && !empty($candidate['email'])) {
                $email = strtolower((string) $candidate['email']);
                break;
            }
        }
    }

    return [
        'provider_user_id' => (string) ($raw['id'] ?? ''),
        'email' => $email,
        'name' => (string) ($raw['name'] ?? $raw['login'] ?? 'GitHub User'),
    ];
}

function upsert_oauth_user(string $provider, array $profile): array
{
    if ($profile['provider_user_id'] === '' || !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
        flash('error', 'OAuth account did not provide a verified email.');
        redirect('index.php?action=login');
    }

    $pdo = db();
    $stmt = $pdo->prepare('
        SELECT users.*
        FROM oauth_accounts
        JOIN users ON users.id = oauth_accounts.user_id
        WHERE oauth_accounts.provider = :provider
          AND oauth_accounts.provider_user_id = :provider_user_id
        LIMIT 1
    ');
    $stmt->execute([
        ':provider' => $provider,
        ':provider_user_id' => $profile['provider_user_id'],
    ]);
    $user = $stmt->fetch();
    if ($user) {
        return $user;
    }

    $user = find_user_by_email($profile['email']);
    if (!$user) {
        $stmt = $pdo->prepare('
            INSERT INTO users (name, email, password_hash, role, is_active)
            VALUES (:name, :email, NULL, "user", 1)
        ');
        $stmt->execute([
            ':name' => $profile['name'],
            ':email' => $profile['email'],
        ]);
        $user = find_user_by_id((int) $pdo->lastInsertId());
    }

    $stmt = $pdo->prepare('
        INSERT OR IGNORE INTO oauth_accounts (user_id, provider, provider_user_id, provider_email)
        VALUES (:user_id, :provider, :provider_user_id, :provider_email)
    ');
    $stmt->execute([
        ':user_id' => (int) $user['id'],
        ':provider' => $provider,
        ':provider_user_id' => $profile['provider_user_id'],
        ':provider_email' => $profile['email'],
    ]);

    return find_user_by_id((int) $user['id']) ?: $user;
}
