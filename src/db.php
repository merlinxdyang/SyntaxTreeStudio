<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    init_schema($pdo);
    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT,
            role TEXT NOT NULL DEFAULT 'user',
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS oauth_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            provider TEXT NOT NULL,
            provider_user_id TEXT NOT NULL,
            provider_email TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(provider, provider_user_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS tree_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            source TEXT NOT NULL,
            latex TEXT NOT NULL,
            node_count INTEGER NOT NULL DEFAULT 0,
            movement_count INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS login_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            email TEXT NOT NULL,
            provider TEXT NOT NULL DEFAULT 'email',
            status TEXT NOT NULL,
            ip_address TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS admin_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS visitor_country_cache (
            ip_address TEXT PRIMARY KEY,
            country_code TEXT,
            country_name TEXT,
            looked_up_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS ip_enrichment (
            ip_hash TEXT PRIMARY KEY,
            ip_address TEXT NOT NULL,
            country_code TEXT,
            country_name TEXT,
            region TEXT,
            city TEXT,
            asn TEXT,
            as_org TEXT,
            as_domain TEXT,
            as_type TEXT,
            isp TEXT,
            organization TEXT,
            rdns TEXT,
            rdap_org TEXT,
            network_name TEXT,
            network_cidr TEXT,
            network_country TEXT,
            network_type TEXT,
            institution_guess TEXT,
            institution_type TEXT,
            confidence INTEGER NOT NULL DEFAULT 0,
            is_academic INTEGER NOT NULL DEFAULT 0,
            is_cloud INTEGER NOT NULL DEFAULT 0,
            is_isp INTEGER NOT NULL DEFAULT 0,
            is_bot INTEGER NOT NULL DEFAULT 0,
            is_proxy INTEGER NOT NULL DEFAULT 0,
            last_checked TEXT
        );

        CREATE INDEX IF NOT EXISTS idx_ip_enrichment_ip_address ON ip_enrichment(ip_address);
        CREATE INDEX IF NOT EXISTS idx_ip_enrichment_asn ON ip_enrichment(asn);
        CREATE INDEX IF NOT EXISTS idx_ip_enrichment_as_org ON ip_enrichment(as_org);
        CREATE INDEX IF NOT EXISTS idx_ip_enrichment_institution ON ip_enrichment(institution_guess);
        CREATE INDEX IF NOT EXISTS idx_ip_enrichment_academic ON ip_enrichment(is_academic);

        CREATE TABLE IF NOT EXISTS visitor_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_key TEXT NOT NULL,
            user_id INTEGER,
            ip_address TEXT,
            country_code TEXT,
            country_name TEXT,
            path TEXT,
            user_agent TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS feedback_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            name TEXT,
            email TEXT,
            message TEXT NOT NULL,
            format TEXT NOT NULL DEFAULT 'markdown',
            status TEXT NOT NULL DEFAULT 'legacy',
            attachment_path TEXT,
            attachment_name TEXT,
            ip_address TEXT,
            user_agent TEXT,
            published_at TEXT,
            edited_at TEXT,
            deleted_at TEXT,
            moderated_by_admin_id INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY(moderated_by_admin_id) REFERENCES admin_accounts(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS feedback_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feedback_id INTEGER NOT NULL UNIQUE,
            admin_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(feedback_id) REFERENCES feedback_messages(id) ON DELETE CASCADE,
            FOREIGN KEY(admin_id) REFERENCES admin_accounts(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS feedback_revisions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feedback_id INTEGER NOT NULL,
            admin_id INTEGER NOT NULL,
            previous_message TEXT NOT NULL,
            previous_format TEXT NOT NULL DEFAULT 'markdown',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(feedback_id) REFERENCES feedback_messages(id) ON DELETE CASCADE,
            FOREIGN KEY(admin_id) REFERENCES admin_accounts(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS site_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_users_created ON users(created_at DESC, id DESC);
        CREATE INDEX IF NOT EXISTS idx_visitor_events_created ON visitor_events(created_at DESC, id DESC);
        CREATE INDEX IF NOT EXISTS idx_feedback_messages_created ON feedback_messages(created_at DESC, id DESC);
    ");

    ensure_table_column($pdo, 'feedback_messages', 'status', "TEXT NOT NULL DEFAULT 'legacy'");
    ensure_table_column($pdo, 'feedback_messages', 'published_at', 'TEXT');
    ensure_table_column($pdo, 'feedback_messages', 'edited_at', 'TEXT');
    ensure_table_column($pdo, 'feedback_messages', 'deleted_at', 'TEXT');
    ensure_table_column($pdo, 'feedback_messages', 'moderated_by_admin_id', 'INTEGER');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_messages_public ON feedback_messages(status, deleted_at, published_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_messages_user_created ON feedback_messages(user_id, created_at DESC)');

    seed_site_settings($pdo);
    seed_standalone_admin($pdo);
}

function ensure_table_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $table) || !preg_match('/^[a-z_][a-z0-9_]*$/i', $column)) {
        throw new InvalidArgumentException('Invalid schema identifier.');
    }
    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $existing) {
        if (($existing['name'] ?? null) === $column) {
            return;
        }
    }
    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function language_options(): array
{
    return [
        'en' => 'English',
        'zh' => '中文',
        'es' => 'Español',
        'ja' => '日本語',
        'ko' => '한국어',
    ];
}

function default_language_codes(): array
{
    return ['zh', 'en', 'es'];
}

function enabled_language_codes(): array
{
    $options = language_options();
    $raw = site_setting('enabled_languages');
    $codes = $raw ? json_decode($raw, true) : null;
    if (!is_array($codes)) {
        return default_language_codes();
    }
    $enabled = array_values(array_filter($codes, fn($code) => is_string($code) && array_key_exists($code, $options)));
    return $enabled ?: default_language_codes();
}

function enabled_language_options(): array
{
    $options = language_options();
    return array_intersect_key($options, array_flip(enabled_language_codes()));
}

function update_enabled_languages(array $codes): void
{
    $options = language_options();
    $enabled = array_values(array_unique(array_filter($codes, fn($code) => is_string($code) && array_key_exists($code, $options))));
    if (!$enabled) {
        throw new InvalidArgumentException('At least one language must remain enabled.');
    }
    set_site_setting('enabled_languages', json_encode($enabled, JSON_UNESCAPED_UNICODE));
}

function site_setting(string $key): ?string
{
    $stmt = db()->prepare('SELECT value FROM site_settings WHERE key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : null;
}

function set_site_setting(string $key, string $value): void
{
    db()->prepare('
        INSERT INTO site_settings (key, value, updated_at)
        VALUES (:key, :value, CURRENT_TIMESTAMP)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP
    ')->execute([
        ':key' => $key,
        ':value' => $value,
    ]);
}

function generation_count(?PDO $pdo = null): int
{
    $connection = $pdo ?? db();
    $stmt = $connection->prepare("SELECT value FROM site_settings WHERE key = 'tree_generation_count' LIMIT 1");
    $stmt->execute();
    return max(0, (int) $stmt->fetchColumn());
}

function increment_generation_count(?PDO $pdo = null): int
{
    $connection = $pdo ?? db();
    $connection->exec("INSERT OR IGNORE INTO site_settings (key, value) VALUES ('tree_generation_count', '0')");
    $connection->exec("
        UPDATE site_settings
        SET value = CAST(value AS INTEGER) + 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE key = 'tree_generation_count'
    ");
    return generation_count($connection);
}

function seed_site_settings(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO site_settings (key, value) VALUES (:key, :value)');
    foreach ([
        'enabled_languages' => json_encode(default_language_codes(), JSON_UNESCAPED_UNICODE),
        'tree_generation_count' => '0',
    ] as $key => $value) {
        $stmt->execute([':key' => $key, ':value' => $value]);
    }
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE lower(email) = lower(:email) LIMIT 1');
    $stmt->execute([':email' => trim($email)]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function find_user_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function create_email_user(string $name, string $email, string $password): int
{
    $stmt = db()->prepare('
        INSERT INTO users (name, email, password_hash, role, is_active)
        VALUES (:name, :email, :password_hash, "user", 1)
    ');
    $stmt->execute([
        ':name' => $name,
        ':email' => strtolower(trim($email)),
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    return (int) db()->lastInsertId();
}

function record_login_attempt(?int $userId, string $email, string $provider, string $status): void
{
    $stmt = db()->prepare('
        INSERT INTO login_audit (user_id, email, provider, status, ip_address)
        VALUES (:user_id, :email, :provider, :status, :ip_address)
    ');
    $stmt->execute([
        ':user_id' => $userId,
        ':email' => $email,
        ':provider' => $provider,
        ':status' => $status,
        ':ip_address' => client_ip_address() ?: null,
    ]);
}

function login_attempt_rate_limited(string $email, string $ipAddress): bool
{
    if ($ipAddress === '' || $email === '') {
        return false;
    }
    $stmt = db()->prepare('
        SELECT COUNT(*)
        FROM login_audit
        WHERE lower(email) = lower(:email)
          AND ip_address = :ip_address
          AND status = "failed"
          AND created_at >= datetime("now", "-15 minutes")
    ');
    $stmt->execute([':email' => trim($email), ':ip_address' => $ipAddress]);
    return (int) $stmt->fetchColumn() >= 10;
}

function registration_rate_limited(string $ipAddress): bool
{
    if ($ipAddress === '') {
        return false;
    }
    $stmt = db()->prepare('
        SELECT COUNT(*)
        FROM login_audit
        WHERE ip_address = :ip_address
          AND status = "registered"
          AND created_at >= datetime("now", "-1 hour")
    ');
    $stmt->execute([':ip_address' => $ipAddress]);
    return (int) $stmt->fetchColumn() >= 5;
}

function save_tree_record(int $userId, string $source, string $latex, int $nodeCount, int $movementCount): int
{
    $stmt = db()->prepare('
        INSERT INTO tree_records (user_id, source, latex, node_count, movement_count)
        VALUES (:user_id, :source, :latex, :node_count, :movement_count)
    ');
    $stmt->execute([
        ':user_id' => $userId,
        ':source' => $source,
        ':latex' => $latex,
        ':node_count' => $nodeCount,
        ':movement_count' => $movementCount,
    ]);

    db()->prepare('
        DELETE FROM tree_records
        WHERE user_id = :user_id
          AND id NOT IN (
            SELECT id FROM tree_records
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT 20
          )
    ')->execute([':user_id' => $userId]);

    return (int) db()->lastInsertId();
}

function recent_tree_records(int $userId): array
{
    $stmt = db()->prepare('
        SELECT id, source, latex, node_count, movement_count, created_at
        FROM tree_records
        WHERE user_id = :user_id
        ORDER BY created_at DESC, id DESC
        LIMIT 20
    ');
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function seed_standalone_admin(PDO $pdo): void
{
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM admin_accounts WHERE username = 'admin'")->fetchColumn();
    if ($exists > 0) {
        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO admin_accounts (username, password_hash)
        VALUES (:username, :password_hash)
    ');
    $stmt->execute([
        ':username' => 'admin',
        ':password_hash' => password_hash('Admin123456', PASSWORD_DEFAULT),
    ]);
}

function find_standalone_admin(string $username): ?array
{
    $stmt = db()->prepare('SELECT * FROM admin_accounts WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => trim($username)]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

function client_ip_address(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $ip = trim((string) $candidate);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '';
}

function record_visitor_event(): void
{
    if (PHP_SAPI === 'cli' || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        return;
    }

    $today = gmdate('Y-m-d');
    if (($_SESSION['visitor_country_logged_on'] ?? '') === $today) {
        return;
    }

    $ip = client_ip_address();
    $country = lookup_country_for_ip($ip);
    $stmt = db()->prepare('
        INSERT INTO visitor_events (session_key, user_id, ip_address, country_code, country_name, path, user_agent)
        VALUES (:session_key, :user_id, :ip_address, :country_code, :country_name, :path, :user_agent)
    ');
    $stmt->execute([
        ':session_key' => session_id(),
        ':user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        ':ip_address' => $ip !== '' ? $ip : null,
        ':country_code' => $country['code'],
        ':country_name' => $country['name'],
        ':path' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 500),
        ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);

    $_SESSION['visitor_country_logged_on'] = $today;
}

function lookup_country_for_ip(string $ip): array
{
    $headerCountry = header_country();
    if ($headerCountry['code'] !== '' || $headerCountry['name'] !== '') {
        return $headerCountry;
    }

    if ($ip === '' || is_private_ip($ip)) {
        return ['code' => 'LOCAL', 'name' => 'Local or private network'];
    }

    $cached = cached_country_for_ip($ip);
    if ($cached) {
        return $cached;
    }

    $country = remote_country_lookup($ip);
    if (country_is_known($country)) {
        cache_country_for_ip($ip, $country);
        update_unknown_visitors_for_ip($ip, $country);
    }
    return $country;
}

function header_country(): array
{
    $code = strtoupper(trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    if ($code !== '' && $code !== 'XX') {
        return ['code' => substr($code, 0, 8), 'name' => $code];
    }

    $geoCode = strtoupper(trim((string) ($_SERVER['GEOIP_COUNTRY_CODE'] ?? $_SERVER['HTTP_X_COUNTRY_CODE'] ?? '')));
    $geoName = trim((string) ($_SERVER['GEOIP_COUNTRY_NAME'] ?? $_SERVER['HTTP_X_COUNTRY_NAME'] ?? ''));
    if ($geoCode !== '' || $geoName !== '') {
        return [
            'code' => substr($geoCode, 0, 8),
            'name' => $geoName !== '' ? substr($geoName, 0, 120) : substr($geoCode, 0, 8),
        ];
    }

    return ['code' => '', 'name' => 'Unknown'];
}

function cached_country_for_ip(string $ip): ?array
{
    $stmt = db()->prepare("
        SELECT country_code, country_name
        FROM visitor_country_cache
        WHERE ip_address = :ip
          AND looked_up_at >= datetime('now', '-30 days')
        LIMIT 1
    ");
    $stmt->execute([':ip' => $ip]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $country = [
        'code' => (string) ($row['country_code'] ?? ''),
        'name' => (string) ($row['country_name'] ?? 'Unknown'),
    ];
    return country_is_known($country) ? $country : null;
}

function cache_country_for_ip(string $ip, array $country): void
{
    if (!country_is_known($country)) {
        db()->prepare('DELETE FROM visitor_country_cache WHERE ip_address = :ip')->execute([':ip' => $ip]);
        return;
    }

    $stmt = db()->prepare('
        INSERT INTO visitor_country_cache (ip_address, country_code, country_name, looked_up_at)
        VALUES (:ip, :code, :name, CURRENT_TIMESTAMP)
        ON CONFLICT(ip_address) DO UPDATE SET
            country_code = excluded.country_code,
            country_name = excluded.country_name,
            looked_up_at = CURRENT_TIMESTAMP
    ');
    $stmt->execute([
        ':ip' => $ip,
        ':code' => $country['code'] ?? '',
        ':name' => $country['name'] ?? 'Unknown',
    ]);
}

function remote_country_lookup(string $ip): array
{
    $lookups = [
        [
            'url' => 'https://ipwho.is/' . rawurlencode($ip),
            'parse' => static function (array $data): array {
                if (($data['success'] ?? false) !== true) {
                    return ['code' => '', 'name' => 'Unknown'];
                }
                return [
                    'code' => substr(strtoupper((string) ($data['country_code'] ?? '')), 0, 8),
                    'name' => substr((string) ($data['country'] ?? 'Unknown'), 0, 120),
                ];
            },
        ],
        [
            'url' => 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,countryCode',
            'parse' => static function (array $data): array {
                if (($data['status'] ?? '') !== 'success') {
                    return ['code' => '', 'name' => 'Unknown'];
                }
                return [
                    'code' => substr(strtoupper((string) ($data['countryCode'] ?? '')), 0, 8),
                    'name' => substr((string) ($data['country'] ?? 'Unknown'), 0, 120),
                ];
            },
        ],
        [
            'url' => 'https://ipapi.co/' . rawurlencode($ip) . '/json/',
            'parse' => static function (array $data): array {
                if (!empty($data['error'])) {
                    return ['code' => '', 'name' => 'Unknown'];
                }
                return [
                    'code' => substr(strtoupper((string) ($data['country_code'] ?? '')), 0, 8),
                    'name' => substr((string) ($data['country_name'] ?? 'Unknown'), 0, 120),
                ];
            },
        ],
    ];

    foreach ($lookups as $lookup) {
        $data = fetch_json_url($lookup['url']);
        if (!$data) {
            continue;
        }
        $country = $lookup['parse']($data);
        if (country_is_known($country)) {
            return $country;
        }
    }

    return ['code' => '', 'name' => 'Unknown'];
}

function fetch_json_url(string $url, ?string $postBody = null, int $timeoutSeconds = 4): ?array
{
    $raw = null;
    $timeoutSeconds = max(1, min(10, $timeoutSeconds));

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl) {
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min(2, $timeoutSeconds),
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_USERAGENT => 'MerlinSyntaxStudio/0.2 visitor country lookup',
            ];
            if ($postBody !== null) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $postBody;
                $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
            }
            curl_setopt_array($curl, $options);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            unset($curl);
            if (is_string($response) && $status >= 200 && $status < 300) {
                $raw = $response;
            }
        }
    }

    if ($raw === null && filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $http = [
            'timeout' => $timeoutSeconds,
            'header' => "User-Agent: MerlinSyntaxStudio/0.2 visitor country lookup\r\n",
        ];
        if ($postBody !== null) {
            $http['method'] = 'POST';
            $http['header'] .= "Content-Type: application/json\r\n";
            $http['content'] = $postBody;
        }
        $context = stream_context_create([
            'http' => $http,
        ]);
        $response = @file_get_contents($url, false, $context);
        if (is_string($response)) {
            $raw = $response;
        }
    }

    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function country_is_known(array $country): bool
{
    $code = strtoupper(trim((string) ($country['code'] ?? '')));
    $name = trim((string) ($country['name'] ?? ''));
    return $code !== '' && $code !== 'UNK' && strtolower($name) !== 'unknown';
}

function update_unknown_visitors_for_ip(string $ip, array $country): void
{
    if (!country_is_known($country)) {
        return;
    }

    $stmt = db()->prepare("
        UPDATE visitor_events
        SET country_code = :code, country_name = :name
        WHERE ip_address = :ip
          AND (
            country_code IS NULL
            OR country_code = ''
            OR country_name IS NULL
            OR country_name = ''
            OR lower(country_name) = 'unknown'
          )
    ");
    $stmt->execute([
        ':ip' => $ip,
        ':code' => $country['code'],
        ':name' => $country['name'],
    ]);
}

function refresh_unknown_visitor_countries(int $limit = 25): void
{
    $stmt = db()->prepare("
        SELECT DISTINCT ip_address
        FROM visitor_events
        WHERE ip_address IS NOT NULL
          AND ip_address != ''
          AND (
            country_code IS NULL
            OR country_code = ''
            OR country_name IS NULL
            OR country_name = ''
            OR lower(country_name) = 'unknown'
          )
        ORDER BY id DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $ips = [];
    foreach ($stmt->fetchAll() as $row) {
        $ip = (string) ($row['ip_address'] ?? '');
        if ($ip === '' || is_private_ip($ip)) {
            continue;
        }
        $ips[] = $ip;
    }

    if (!$ips) {
        return;
    }

    $found = remote_country_lookup_batch($ips);
    foreach ($found as $ip => $country) {
        if (country_is_known($country)) {
            cache_country_for_ip($ip, $country);
            update_unknown_visitors_for_ip($ip, $country);
        }
    }

    foreach ($ips as $ip) {
        if (isset($found[$ip])) {
            continue;
        }
        $country = remote_country_lookup($ip);
        if (country_is_known($country)) {
            cache_country_for_ip($ip, $country);
            update_unknown_visitors_for_ip($ip, $country);
        }
    }
}

function remote_country_lookup_batch(array $ips): array
{
    $ips = array_values(array_unique(array_filter(array_map('strval', $ips))));
    if (!$ips) {
        return [];
    }

    $data = fetch_json_url(
        'http://ip-api.com/batch?fields=status,country,countryCode,query',
        json_encode($ips, JSON_UNESCAPED_SLASHES)
    );
    if (!is_array($data)) {
        return [];
    }

    $countries = [];
    foreach ($data as $row) {
        if (!is_array($row) || ($row['status'] ?? '') !== 'success') {
            continue;
        }
        $ip = (string) ($row['query'] ?? '');
        if ($ip === '') {
            continue;
        }
        $country = [
            'code' => substr(strtoupper((string) ($row['countryCode'] ?? '')), 0, 8),
            'name' => substr((string) ($row['country'] ?? 'Unknown'), 0, 120),
        ];
        if (country_is_known($country)) {
            $countries[$ip] = $country;
        }
    }

    return $countries;
}

function is_private_ip(string $ip): bool
{
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function ip_hash_for_address(string $ip): string
{
    $salt = getenv('SYNTREE_IP_HASH_SALT');
    if (!is_string($salt) || $salt === '') {
        $salt = SESSION_NAME . '|ip-enrichment';
    }
    return hash('sha256', $salt . '|' . strtolower(trim($ip)));
}

function ip_enrichment_pending_count(): int
{
    return (int) db()->query("
        SELECT COUNT(*) FROM (
            SELECT v.ip_address
            FROM visitor_events v
            LEFT JOIN ip_enrichment e ON e.ip_address = v.ip_address
            WHERE v.ip_address IS NOT NULL
              AND v.ip_address != ''
              AND e.ip_hash IS NULL
            GROUP BY v.ip_address
        )
    ")->fetchColumn();
}

function refresh_unknown_ip_enrichments(int $limit = 10): int
{
    $stmt = db()->prepare("
        SELECT
            ip_address,
            COALESCE(MAX(NULLIF(country_code, '')), '') AS country_code,
            COALESCE(MAX(NULLIF(country_name, '')), '') AS country_name,
            MAX(created_at) AS latest_visit
        FROM visitor_events
        WHERE ip_address IS NOT NULL
          AND ip_address != ''
        GROUP BY ip_address
        ORDER BY latest_visit DESC
        LIMIT 1000
    ");
    $stmt->execute();

    $checked = 0;
    foreach ($stmt->fetchAll() as $row) {
        if ($checked >= max(1, $limit)) {
            break;
        }

        $ip = trim((string) ($row['ip_address'] ?? ''));
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            continue;
        }

        $hash = ip_hash_for_address($ip);
        $exists = db()->prepare('SELECT 1 FROM ip_enrichment WHERE ip_hash = :hash LIMIT 1');
        $exists->execute([':hash' => $hash]);
        if ($exists->fetchColumn()) {
            continue;
        }

        save_ip_enrichment(build_ip_enrichment($ip, [
            'code' => (string) ($row['country_code'] ?? ''),
            'name' => (string) ($row['country_name'] ?? ''),
        ]));
        $checked++;
    }

    return $checked;
}

function build_ip_enrichment(string $ip, array $fallbackCountry = []): array
{
    $data = [
        'ip_hash' => ip_hash_for_address($ip),
        'ip_address' => $ip,
        'country_code' => substr(strtoupper((string) ($fallbackCountry['code'] ?? '')), 0, 8),
        'country_name' => substr((string) ($fallbackCountry['name'] ?? ''), 0, 120),
        'region' => '',
        'city' => '',
        'asn' => '',
        'as_org' => '',
        'as_domain' => '',
        'as_type' => '',
        'isp' => '',
        'organization' => '',
        'rdns' => '',
        'rdap_org' => '',
        'network_name' => '',
        'network_cidr' => '',
        'network_country' => '',
        'network_type' => '',
        'institution_guess' => '',
        'institution_type' => '',
        'confidence' => 0,
        'is_academic' => 0,
        'is_cloud' => 0,
        'is_isp' => 0,
        'is_bot' => 0,
        'is_proxy' => 0,
        'last_checked' => gmdate('Y-m-d H:i:s'),
    ];

    if (is_private_ip($ip)) {
        $data['network_type'] = 'private';
        $data['institution_guess'] = 'Local or private network';
        $data['institution_type'] = 'private';
        return $data;
    }

    foreach (remote_ip_enrichment($ip) as $key => $value) {
        if (array_key_exists($key, $data) && $data[$key] === '' && is_string($value) && $value !== '') {
            $data[$key] = $value;
        }
        if (in_array($key, ['is_cloud', 'is_proxy'], true) && (int) $value === 1) {
            $data[$key] = 1;
        }
    }

    return classify_ip_institution($data);
}

function remote_ip_enrichment(string $ip): array
{
    $data = [];

    $ipApi = fetch_json_url('http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,countryCode,regionName,city,as,asname,org,isp,hosting,proxy,mobile,query', null, 2);
    if (is_array($ipApi) && ($ipApi['status'] ?? '') === 'success') {
        enrichment_set_if_blank($data, 'country_code', strtoupper((string) ($ipApi['countryCode'] ?? '')), 8);
        enrichment_set_if_blank($data, 'country_name', (string) ($ipApi['country'] ?? ''), 120);
        enrichment_set_if_blank($data, 'region', (string) ($ipApi['regionName'] ?? ''), 128);
        enrichment_set_if_blank($data, 'city', (string) ($ipApi['city'] ?? ''), 128);
        enrichment_set_if_blank($data, 'organization', (string) ($ipApi['org'] ?? ''), 255);
        enrichment_set_if_blank($data, 'isp', (string) ($ipApi['isp'] ?? ''), 255);
        [$asn, $org] = parse_as_text((string) ($ipApi['as'] ?? ''));
        enrichment_set_if_blank($data, 'asn', $asn, 32);
        enrichment_set_if_blank($data, 'as_org', $org !== '' ? $org : (string) ($ipApi['asname'] ?? ''), 255);
        if (!empty($ipApi['hosting'])) {
            $data['is_cloud'] = 1;
            enrichment_set_if_blank($data, 'network_type', 'cloud', 64);
        }
        if (!empty($ipApi['proxy'])) {
            $data['is_proxy'] = 1;
        }
        if (!empty($ipApi['mobile'])) {
            enrichment_set_if_blank($data, 'network_type', 'mobile', 64);
        }
    }

    $needsFallback = empty($data['as_org']) && empty($data['organization']) && empty($data['isp']);
    $ipWho = $needsFallback ? fetch_json_url('https://ipwho.is/' . rawurlencode($ip), null, 2) : null;
    if (is_array($ipWho) && ($ipWho['success'] ?? false) === true) {
        enrichment_set_if_blank($data, 'country_code', strtoupper((string) ($ipWho['country_code'] ?? '')), 8);
        enrichment_set_if_blank($data, 'country_name', (string) ($ipWho['country'] ?? ''), 120);
        enrichment_set_if_blank($data, 'region', (string) ($ipWho['region'] ?? ''), 128);
        enrichment_set_if_blank($data, 'city', (string) ($ipWho['city'] ?? ''), 128);
        $connection = is_array($ipWho['connection'] ?? null) ? $ipWho['connection'] : [];
        $asn = (string) ($connection['asn'] ?? '');
        if ($asn !== '' && stripos($asn, 'AS') !== 0) {
            $asn = 'AS' . $asn;
        }
        enrichment_set_if_blank($data, 'asn', $asn, 32);
        enrichment_set_if_blank($data, 'as_org', (string) ($connection['org'] ?? ''), 255);
        enrichment_set_if_blank($data, 'isp', (string) ($connection['isp'] ?? ''), 255);
        enrichment_set_if_blank($data, 'as_domain', (string) ($connection['domain'] ?? ''), 255);
        enrichment_set_if_blank($data, 'as_type', (string) ($connection['type'] ?? ''), 64);
    }

    return $data;
}

function enrichment_set_if_blank(array &$data, string $key, string $value, int $maxLength): void
{
    $value = trim($value);
    if ($value === '' || ($data[$key] ?? '') !== '') {
        return;
    }
    $data[$key] = mb_substr($value, 0, $maxLength);
}

function parse_as_text(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return ['', ''];
    }
    if (preg_match('/^(AS\d+)\s+(.+)$/i', $value, $matches)) {
        return [strtoupper($matches[1]), trim($matches[2])];
    }
    if (preg_match('/^(AS\d+)$/i', $value, $matches)) {
        return [strtoupper($matches[1]), ''];
    }
    return ['', $value];
}

function rdap_org_name(array $rdap): string
{
    foreach (($rdap['entities'] ?? []) as $entity) {
        if (!is_array($entity)) {
            continue;
        }
        $name = rdap_vcard_name($entity);
        if ($name !== '') {
            return $name;
        }
    }
    return '';
}

function rdap_vcard_name(array $entity): string
{
    $vcard = $entity['vcardArray'][1] ?? null;
    if (is_array($vcard)) {
        foreach (['org', 'fn'] as $fieldName) {
            foreach ($vcard as $field) {
                if (is_array($field) && ($field[0] ?? '') === $fieldName && is_string($field[3] ?? null)) {
                    return trim((string) $field[3]);
                }
            }
        }
    }

    foreach (($entity['entities'] ?? []) as $child) {
        if (is_array($child)) {
            $name = rdap_vcard_name($child);
            if ($name !== '') {
                return $name;
            }
        }
    }

    return '';
}

function rdap_network_range(array $rdap): string
{
    $start = trim((string) ($rdap['startAddress'] ?? ''));
    $end = trim((string) ($rdap['endAddress'] ?? ''));
    if ($start !== '' && $end !== '') {
        return $start . ' - ' . $end;
    }
    return '';
}

function classify_ip_institution(array $data): array
{
    $candidateFields = ['rdap_org', 'organization', 'as_org', 'isp', 'network_name', 'as_domain', 'rdns'];
    $candidates = [];
    foreach ($candidateFields as $field) {
        $value = trim((string) ($data[$field] ?? ''));
        if ($value !== '') {
            $candidates[] = $value;
        }
    }
    $joined = mb_strtolower(implode(' ', $candidates));

    $academicKeywords = [
        'university', 'college', 'institute of technology', 'polytechnic', 'research',
        'laboratory', 'academy', 'campus', '.edu', '.ac.', 'ac.uk', 'universite',
        'université', 'universidad', 'universidade', 'universitat', 'universität',
        'universita', 'università', 'hochschule', 'chinese academy', 'academia sinica',
        'max planck', 'cnrs', 'riken', 'kaist', 'cern', 'csic', 'inria', 'nih', 'nist',
    ];
    $cloudKeywords = [
        'amazon technologies', 'amazon web services', 'aws', 'google cloud', 'microsoft azure',
        'cloudflare', 'digitalocean', 'linode', 'akamai', 'ovh', 'hetzner', 'vultr',
        'oracle cloud', 'alibaba cloud', 'tencent cloud', 'huawei cloud',
    ];
    $ispKeywords = [
        'broadband', 'telecom', 'telecommunications', 'cable', 'wireless', 'mobile',
        'cellular', 'residential', 'internet service', 'comcast', 'charter', 'spectrum',
        'verizon', 'at&t', 't-mobile', 'vodafone', 'china mobile', 'china telecom',
        'china unicom', 'deutsche telekom', 'telefonica', 'orange', 'bt ', 'virgin media',
        'rogers', 'bell canada',
    ];
    $botKeywords = ['bot', 'crawler', 'spider', 'scan', 'uptime', 'monitor'];

    $isAcademic = contains_any_text($joined, $academicKeywords);
    $isCloud = (int) ($data['is_cloud'] ?? 0) === 1 || contains_any_text($joined, $cloudKeywords);
    $isIsp = contains_any_text($joined, $ispKeywords);
    $isBot = contains_any_text($joined, $botKeywords);

    $data['is_academic'] = $isAcademic ? 1 : 0;
    $data['is_cloud'] = $isCloud ? 1 : (int) ($data['is_cloud'] ?? 0);
    $data['is_isp'] = $isIsp ? 1 : 0;
    $data['is_bot'] = $isBot ? 1 : 0;

    if ($isAcademic) {
        $data['institution_guess'] = best_institution_candidate($candidates, $academicKeywords);
        $data['institution_type'] = contains_any_text(mb_strtolower($data['institution_guess']), ['research', 'laboratory', 'academy', 'cnrs', 'riken', 'inria', 'nih', 'nist'])
            ? 'research'
            : 'academic';
        $data['confidence'] = contains_any_text(mb_strtolower((string) ($data['rdns'] ?? '')), ['.edu', '.ac.']) ? 90 : 80;
        return $data;
    }

    $guess = best_institution_candidate($candidates);
    if ($guess === '') {
        $guess = 'Unknown network';
    }
    $data['institution_guess'] = $guess;

    if ($isCloud) {
        $data['institution_type'] = 'cloud';
        $data['confidence'] = 25;
    } elseif ((int) ($data['is_proxy'] ?? 0) === 1) {
        $data['institution_type'] = 'proxy';
        $data['confidence'] = 20;
    } elseif ($isIsp) {
        $data['institution_type'] = 'isp';
        $data['confidence'] = 25;
    } elseif ($guess !== 'Unknown network') {
        $data['institution_type'] = 'organization';
        $data['confidence'] = 45;
    } else {
        $data['institution_type'] = 'unknown';
        $data['confidence'] = 10;
    }

    return $data;
}

function contains_any_text(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        $needle = mb_strtolower((string) $needle);
        if ($needle !== '' && strpos($haystack, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function best_institution_candidate(array $candidates, array $preferredKeywords = []): string
{
    foreach ($candidates as $candidate) {
        $candidate = clean_institution_name((string) $candidate);
        if ($candidate === '') {
            continue;
        }
        if ($preferredKeywords && !contains_any_text(mb_strtolower($candidate), $preferredKeywords)) {
            continue;
        }
        return $candidate;
    }

    foreach ($candidates as $candidate) {
        $candidate = clean_institution_name((string) $candidate);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function clean_institution_name(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    $value = preg_replace('/^AS\d+\s+/i', '', $value) ?? $value;
    return mb_substr(trim($value, " \t\n\r\0\x0B,.;"), 0, 255);
}

function save_ip_enrichment(array $data): void
{
    $columns = [
        'ip_hash', 'ip_address', 'country_code', 'country_name', 'region', 'city',
        'asn', 'as_org', 'as_domain', 'as_type', 'isp', 'organization', 'rdns',
        'rdap_org', 'network_name', 'network_cidr', 'network_country', 'network_type',
        'institution_guess', 'institution_type', 'confidence', 'is_academic',
        'is_cloud', 'is_isp', 'is_bot', 'is_proxy', 'last_checked',
    ];
    $params = [];
    foreach ($columns as $column) {
        $params[':' . $column] = $data[$column] ?? (strpos($column, 'is_') === 0 || $column === 'confidence' ? 0 : '');
    }

    db()->prepare('
        INSERT INTO ip_enrichment (
            ip_hash, ip_address, country_code, country_name, region, city,
            asn, as_org, as_domain, as_type, isp, organization, rdns,
            rdap_org, network_name, network_cidr, network_country, network_type,
            institution_guess, institution_type, confidence, is_academic,
            is_cloud, is_isp, is_bot, is_proxy, last_checked
        ) VALUES (
            :ip_hash, :ip_address, :country_code, :country_name, :region, :city,
            :asn, :as_org, :as_domain, :as_type, :isp, :organization, :rdns,
            :rdap_org, :network_name, :network_cidr, :network_country, :network_type,
            :institution_guess, :institution_type, :confidence, :is_academic,
            :is_cloud, :is_isp, :is_bot, :is_proxy, :last_checked
        )
        ON CONFLICT(ip_hash) DO UPDATE SET
            ip_address = excluded.ip_address,
            country_code = excluded.country_code,
            country_name = excluded.country_name,
            region = excluded.region,
            city = excluded.city,
            asn = excluded.asn,
            as_org = excluded.as_org,
            as_domain = excluded.as_domain,
            as_type = excluded.as_type,
            isp = excluded.isp,
            organization = excluded.organization,
            rdns = excluded.rdns,
            rdap_org = excluded.rdap_org,
            network_name = excluded.network_name,
            network_cidr = excluded.network_cidr,
            network_country = excluded.network_country,
            network_type = excluded.network_type,
            institution_guess = excluded.institution_guess,
            institution_type = excluded.institution_type,
            confidence = excluded.confidence,
            is_academic = excluded.is_academic,
            is_cloud = excluded.is_cloud,
            is_isp = excluded.is_isp,
            is_bot = excluded.is_bot,
            is_proxy = excluded.is_proxy,
            last_checked = excluded.last_checked
    ')->execute($params);
}

function institution_summary_conditions(string $filter): array
{
    $where = [
        "v.ip_address IS NOT NULL",
        "v.ip_address != ''",
    ];
    if ($filter === 'academic') {
        $where[] = 'COALESCE(e.is_academic, 0) = 1';
    } elseif ($filter === 'hide_networks') {
        $where[] = 'COALESCE(e.is_cloud, 0) = 0';
        $where[] = 'COALESCE(e.is_isp, 0) = 0';
        $where[] = 'COALESCE(e.is_bot, 0) = 0';
        $where[] = 'COALESCE(e.is_proxy, 0) = 0';
    }

    return $where;
}

function institution_summary_count(string $filter = 'all'): int
{
    $where = institution_summary_conditions($filter);
    return (int) db()->query("
        SELECT COUNT(*) FROM (
            SELECT 1
            FROM visitor_events v
            LEFT JOIN ip_enrichment e ON e.ip_address = v.ip_address
            WHERE " . implode(' AND ', $where) . "
            GROUP BY
                COALESCE(NULLIF(e.institution_guess, ''), 'Not checked'),
                COALESCE(NULLIF(e.institution_type, ''), 'unchecked')
        )
    ")->fetchColumn();
}

function institution_summary_rows(string $filter = 'all', int $limit = 100, int $offset = 0): array
{
    $where = institution_summary_conditions($filter);

    $stmt = db()->prepare("
        SELECT
            COALESCE(NULLIF(e.institution_guess, ''), 'Not checked') AS institution_guess,
            COALESCE(NULLIF(e.institution_type, ''), 'unchecked') AS institution_type,
            MAX(COALESCE(e.confidence, 0)) AS confidence,
            MAX(COALESCE(NULLIF(e.asn, ''), '')) AS asn,
            MAX(COALESCE(NULLIF(e.as_org, ''), NULLIF(e.organization, ''), NULLIF(e.isp, ''), '')) AS network_org,
            MAX(COALESCE(NULLIF(e.rdns, ''), '')) AS rdns,
            COALESCE(MAX(NULLIF(e.country_code, '')), MAX(NULLIF(v.country_code, '')), '') AS country_code,
            COALESCE(MAX(NULLIF(e.country_name, '')), MAX(NULLIF(v.country_name, '')), 'Unknown') AS country_name,
            MAX(COALESCE(e.is_academic, 0)) AS is_academic,
            MAX(COALESCE(e.is_cloud, 0)) AS is_cloud,
            MAX(COALESCE(e.is_isp, 0)) AS is_isp,
            MAX(COALESCE(e.is_bot, 0)) AS is_bot,
            COUNT(DISTINCT v.ip_address) AS ip_count,
            COUNT(*) AS visits,
            COUNT(DISTINCT v.session_key) AS sessions,
            MIN(v.created_at) AS first_visit,
            MAX(v.created_at) AS latest_visit,
            MAX(e.last_checked) AS last_checked
        FROM visitor_events v
        LEFT JOIN ip_enrichment e ON e.ip_address = v.ip_address
        WHERE " . implode(' AND ', $where) . "
        GROUP BY
            COALESCE(NULLIF(e.institution_guess, ''), 'Not checked'),
            COALESCE(NULLIF(e.institution_type, ''), 'unchecked')
        ORDER BY
            MAX(COALESCE(e.is_academic, 0)) DESC,
            MAX(COALESCE(e.confidence, 0)) DESC,
            COUNT(*) DESC,
            institution_guess ASC
        LIMIT :limit
        OFFSET :offset
    ");
    $stmt->bindValue(':limit', max(1, min(5000, $limit)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function visitor_country_summary_count(?string $sinceUtc = null): int
{
    $where = $sinceUtc !== null ? 'WHERE visitor_events.created_at >= :since' : '';
    $stmt = db()->prepare("
        SELECT COUNT(*) FROM (
            SELECT 1
            FROM visitor_events
            {$where}
            GROUP BY COALESCE(NULLIF(country_name, ''), 'Unknown'), COALESCE(NULLIF(country_code, ''), 'UNK')
        )
    ");
    if ($sinceUtc !== null) {
        $stmt->bindValue(':since', $sinceUtc);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function visitor_country_summary(?string $sinceUtc = null, int $limit = 100, int $offset = 0): array
{
    $where = $sinceUtc !== null ? 'WHERE visitor_events.created_at >= :since' : '';
    $stmt = db()->prepare("
        SELECT
            COALESCE(NULLIF(country_name, ''), 'Unknown') AS country_name,
            COALESCE(NULLIF(country_code, ''), 'UNK') AS country_code,
            COUNT(*) AS visits,
            COUNT(DISTINCT session_key) AS visitors,
            MAX(created_at) AS latest_visit
        FROM visitor_events
        {$where}
        GROUP BY COALESCE(NULLIF(country_name, ''), 'Unknown'), COALESCE(NULLIF(country_code, ''), 'UNK')
        ORDER BY visitors DESC, visits DESC, country_name ASC
        LIMIT :limit
        OFFSET :offset
    ");
    $stmt->bindValue(':limit', max(1, min(5000, $limit)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    if ($sinceUtc !== null) {
        $stmt->bindValue(':since', $sinceUtc);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function recent_visitor_events(int $limit = 50, ?string $sinceUtc = null, int $offset = 0): array
{
    $where = $sinceUtc !== null ? 'WHERE visitor_events.created_at >= :since' : '';
    $stmt = db()->prepare('
        SELECT visitor_events.*, users.email AS user_email
        FROM visitor_events
        LEFT JOIN users ON users.id = visitor_events.user_id
        ' . $where . '
        ORDER BY visitor_events.created_at DESC, visitor_events.id DESC
        LIMIT :limit
        OFFSET :offset
    ');
    $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    if ($sinceUtc !== null) {
        $stmt->bindValue(':since', $sinceUtc);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function visitor_events_count(?string $sinceUtc = null): int
{
    if ($sinceUtc === null) {
        return (int) db()->query('SELECT COUNT(*) FROM visitor_events')->fetchColumn();
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM visitor_events WHERE created_at >= :since');
    $stmt->execute([':since' => $sinceUtc]);
    return (int) $stmt->fetchColumn();
}

function visitor_country_count(?string $sinceUtc = null): int
{
    if ($sinceUtc === null) {
        return (int) db()->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(country_code, ''), 'UNK')) FROM visitor_events")->fetchColumn();
    }

    $stmt = db()->prepare("SELECT COUNT(DISTINCT COALESCE(NULLIF(country_code, ''), 'UNK')) FROM visitor_events WHERE created_at >= :since");
    $stmt->execute([':since' => $sinceUtc]);
    return (int) $stmt->fetchColumn();
}

function all_visitor_events(): array
{
    return db()->query('
        SELECT visitor_events.*, users.email AS user_email
        FROM visitor_events
        LEFT JOIN users ON users.id = visitor_events.user_id
        ORDER BY visitor_events.created_at DESC, visitor_events.id DESC
    ')->fetchAll();
}

function admin_user_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function admin_user_rows(int $limit = 50, int $offset = 0): array
{
    $stmt = db()->prepare("
        SELECT
            users.id,
            users.name,
            users.email,
            users.role,
            users.is_active,
            users.password_hash,
            users.created_at,
            COUNT(tree_records.id) AS saved_trees,
            GROUP_CONCAT(DISTINCT oauth_accounts.provider) AS providers
        FROM users
        LEFT JOIN tree_records ON tree_records.user_id = users.id
        LEFT JOIN oauth_accounts ON oauth_accounts.user_id = users.id
        GROUP BY users.id
        ORDER BY users.created_at DESC, users.id DESC
        LIMIT :limit
        OFFSET :offset
    ");
    $stmt->bindValue(':limit', max(1, min(5000, $limit)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function save_feedback_message(array $data): int
{
    $status = in_array(($data['status'] ?? ''), ['pending', 'published', 'legacy'], true)
        ? (string) $data['status']
        : 'pending';
    $publishedAt = $status === 'published' ? ($data['published_at'] ?? gmdate('Y-m-d H:i:s')) : null;
    $stmt = db()->prepare('
        INSERT INTO feedback_messages (
            user_id, name, email, message, format, status, attachment_path, attachment_name,
            ip_address, user_agent, published_at
        ) VALUES (
            :user_id, :name, :email, :message, :format, :status, :attachment_path, :attachment_name,
            :ip_address, :user_agent, :published_at
        )
    ');
    $stmt->execute([
        ':user_id' => $data['user_id'] ?? null,
        ':name' => $data['name'] ?? null,
        ':email' => $data['email'] ?? null,
        ':message' => $data['message'],
        ':format' => $data['format'] ?? 'markdown',
        ':status' => $status,
        ':attachment_path' => $data['attachment_path'] ?? null,
        ':attachment_name' => $data['attachment_name'] ?? null,
        ':ip_address' => $data['ip_address'] ?? null,
        ':user_agent' => $data['user_agent'] ?? null,
        ':published_at' => $publishedAt,
    ]);
    return (int) db()->lastInsertId();
}

function public_feedback_messages_count(?int $viewerUserId = null): int
{
    $sql = '
        SELECT COUNT(*)
        FROM feedback_messages
        WHERE deleted_at IS NULL
          AND (status = "published"' . ($viewerUserId ? ' OR (status = "pending" AND user_id = :viewer_user_id)' : '') . ')
    ';
    $stmt = db()->prepare($sql);
    if ($viewerUserId) {
        $stmt->bindValue(':viewer_user_id', $viewerUserId, PDO::PARAM_INT);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function public_feedback_messages(int $limit = 20, int $offset = 0, ?int $viewerUserId = null): array
{
    $sql = '
        SELECT
            feedback_messages.id,
            feedback_messages.user_id,
            feedback_messages.message,
            feedback_messages.format,
            feedback_messages.status,
            feedback_messages.attachment_path,
            feedback_messages.created_at,
            feedback_messages.published_at,
            feedback_messages.edited_at,
            CASE WHEN instr(users.name, "@") = 0 THEN users.name ELSE NULL END AS public_name,
            feedback_replies.message AS admin_reply,
            feedback_replies.created_at AS admin_reply_created_at,
            feedback_replies.updated_at AS admin_reply_updated_at
        FROM feedback_messages
        INNER JOIN users ON users.id = feedback_messages.user_id
        LEFT JOIN feedback_replies ON feedback_replies.feedback_id = feedback_messages.id
        WHERE feedback_messages.deleted_at IS NULL
          AND (feedback_messages.status = "published"' . ($viewerUserId ? ' OR (feedback_messages.status = "pending" AND feedback_messages.user_id = :viewer_user_id)' : '') . ')
        ORDER BY COALESCE(feedback_messages.published_at, feedback_messages.created_at) DESC, feedback_messages.id DESC
        LIMIT :limit
        OFFSET :offset
    ';
    $stmt = db()->prepare($sql);
    if ($viewerUserId) {
        $stmt->bindValue(':viewer_user_id', $viewerUserId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function feedback_user_has_published_message(int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM feedback_messages WHERE user_id = :user_id AND status = "published" AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([':user_id' => $userId]);
    return (bool) $stmt->fetchColumn();
}

function feedback_status_for_new_message(int $userId): string
{
    return feedback_user_has_published_message($userId) ? 'published' : 'pending';
}

function normalize_feedback_duplicate_text(string $message): string
{
    $message = mb_strtolower(trim($message));
    return preg_replace('/\s+/u', ' ', $message) ?? $message;
}

function feedback_submission_violation(int $userId, string $ipAddress, string $message): ?string
{
    $recent = db()->prepare('
        SELECT message
        FROM feedback_messages
        WHERE user_id = :user_id
          AND created_at >= datetime("now", "-7 days")
        ORDER BY created_at DESC
        LIMIT 100
    ');
    $recent->execute([':user_id' => $userId]);
    $normalized = normalize_feedback_duplicate_text($message);
    foreach ($recent->fetchAll() as $row) {
        if (hash_equals(normalize_feedback_duplicate_text((string) $row['message']), $normalized)) {
            return 'duplicate';
        }
    }

    $cooldown = db()->prepare('
        SELECT 1 FROM feedback_messages
        WHERE user_id = :user_id AND created_at >= datetime("now", "-60 seconds")
        LIMIT 1
    ');
    $cooldown->execute([':user_id' => $userId]);
    if ($cooldown->fetchColumn()) {
        return 'cooldown';
    }

    $dailyUser = db()->prepare('
        SELECT COUNT(*) FROM feedback_messages
        WHERE user_id = :user_id AND created_at >= datetime("now", "-1 day")
    ');
    $dailyUser->execute([':user_id' => $userId]);
    if ((int) $dailyUser->fetchColumn() >= 5) {
        return 'user_daily';
    }

    if ($ipAddress !== '') {
        $dailyIp = db()->prepare('
            SELECT COUNT(*) FROM feedback_messages
            WHERE ip_address = :ip_address AND created_at >= datetime("now", "-1 day")
        ');
        $dailyIp->execute([':ip_address' => $ipAddress]);
        if ((int) $dailyIp->fetchColumn() >= 20) {
            return 'ip_daily';
        }
    }

    return null;
}

function feedback_message_by_id(int $feedbackId): ?array
{
    $stmt = db()->prepare('
        SELECT feedback_messages.*, users.name AS user_name, users.email AS user_email,
               feedback_replies.message AS admin_reply,
               feedback_replies.updated_at AS admin_reply_updated_at
        FROM feedback_messages
        LEFT JOIN users ON users.id = feedback_messages.user_id
        LEFT JOIN feedback_replies ON feedback_replies.feedback_id = feedback_messages.id
        WHERE feedback_messages.id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $feedbackId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function publish_feedback_message(int $feedbackId, int $adminId): void
{
    $stmt = db()->prepare('
        UPDATE feedback_messages
        SET status = "published",
            published_at = COALESCE(published_at, CURRENT_TIMESTAMP),
            moderated_by_admin_id = :admin_id,
            deleted_at = NULL
        WHERE id = :id
    ');
    $stmt->execute([':admin_id' => $adminId, ':id' => $feedbackId]);
}

function save_admin_feedback_reply(int $feedbackId, int $adminId, string $message): void
{
    db()->prepare('
        INSERT INTO feedback_replies (feedback_id, admin_id, message)
        VALUES (:feedback_id, :admin_id, :message)
        ON CONFLICT(feedback_id) DO UPDATE SET
            admin_id = excluded.admin_id,
            message = excluded.message,
            updated_at = CURRENT_TIMESTAMP
    ')->execute([
        ':feedback_id' => $feedbackId,
        ':admin_id' => $adminId,
        ':message' => $message,
    ]);
}

function delete_admin_feedback_reply(int $feedbackId): void
{
    db()->prepare('DELETE FROM feedback_replies WHERE feedback_id = :feedback_id')
        ->execute([':feedback_id' => $feedbackId]);
}

function edit_feedback_message_by_admin(int $feedbackId, int $adminId, string $message): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $current = feedback_message_by_id($feedbackId);
        if (!$current) {
            throw new InvalidArgumentException('Feedback message not found.');
        }
        $pdo->prepare('
            INSERT INTO feedback_revisions (feedback_id, admin_id, previous_message, previous_format)
            VALUES (:feedback_id, :admin_id, :previous_message, :previous_format)
        ')->execute([
            ':feedback_id' => $feedbackId,
            ':admin_id' => $adminId,
            ':previous_message' => (string) $current['message'],
            ':previous_format' => (string) $current['format'],
        ]);
        $pdo->prepare('
            UPDATE feedback_messages
            SET message = :message, format = "markdown", edited_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ')->execute([':message' => $message, ':id' => $feedbackId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function soft_delete_feedback_message(int $feedbackId): void
{
    db()->prepare('UPDATE feedback_messages SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute([':id' => $feedbackId]);
}

function restore_feedback_message(int $feedbackId): void
{
    db()->prepare('UPDATE feedback_messages SET deleted_at = NULL WHERE id = :id')
        ->execute([':id' => $feedbackId]);
}

function purge_feedback_message(int $feedbackId): void
{
    db()->prepare('DELETE FROM feedback_messages WHERE id = :id')->execute([':id' => $feedbackId]);
}

function feedback_admin_filter_sql(string $filter): string
{
    return match ($filter) {
        'pending' => 'feedback_messages.deleted_at IS NULL AND feedback_messages.status = "pending"',
        'open' => 'feedback_messages.deleted_at IS NULL AND feedback_messages.status = "published" AND feedback_replies.id IS NULL',
        'answered' => 'feedback_messages.deleted_at IS NULL AND feedback_messages.status = "published" AND feedback_replies.id IS NOT NULL',
        'deleted' => 'feedback_messages.deleted_at IS NOT NULL',
        'legacy' => 'feedback_messages.deleted_at IS NULL AND feedback_messages.status = "legacy"',
        default => 'feedback_messages.deleted_at IS NULL',
    };
}

function feedback_messages_count(string $filter = 'all'): int
{
    $sql = '
        SELECT COUNT(*)
        FROM feedback_messages
        LEFT JOIN feedback_replies ON feedback_replies.feedback_id = feedback_messages.id
        WHERE ' . feedback_admin_filter_sql($filter);
    return (int) db()->query($sql)->fetchColumn();
}

function recent_feedback_messages(int $limit = 50, int $offset = 0, string $filter = 'all'): array
{
    $stmt = db()->prepare('
        SELECT feedback_messages.*, users.name AS user_name, users.email AS user_email,
               feedback_replies.message AS admin_reply,
               feedback_replies.created_at AS admin_reply_created_at,
               feedback_replies.updated_at AS admin_reply_updated_at
        FROM feedback_messages
        LEFT JOIN users ON users.id = feedback_messages.user_id
        LEFT JOIN feedback_replies ON feedback_replies.feedback_id = feedback_messages.id
        WHERE ' . feedback_admin_filter_sql($filter) . '
        ORDER BY feedback_messages.created_at DESC, feedback_messages.id DESC
        LIMIT :limit
        OFFSET :offset
    ');
    $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
