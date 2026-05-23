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
    ");

    seed_admin($pdo);
}

function seed_admin(PDO $pdo): void
{
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($exists > 0) {
        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, role, is_active)
        VALUES (:name, :email, :password_hash, :role, 1)
    ');
    $stmt->execute([
        ':name' => 'admin',
        ':email' => 'admin@syntree.local',
        ':password_hash' => password_hash('admin123456', PASSWORD_DEFAULT),
        ':role' => 'admin',
    ]);
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
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
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

