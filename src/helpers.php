<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('CSRF verification failed.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function issue_feedback_form_token(): string
{
    $now = time();
    $tokens = is_array($_SESSION['feedback_form_tokens'] ?? null) ? $_SESSION['feedback_form_tokens'] : [];
    $tokens = array_filter($tokens, static fn($createdAt) => is_int($createdAt) && $createdAt >= $now - 7200);
    if (count($tokens) >= 10) {
        asort($tokens);
        $tokens = array_slice($tokens, -9, null, true);
    }
    $token = bin2hex(random_bytes(24));
    $tokens[$token] = $now;
    $_SESSION['feedback_form_tokens'] = $tokens;
    return $token;
}

function consume_feedback_form_token(string $token, int $minimumAgeSeconds = 2): bool
{
    $tokens = is_array($_SESSION['feedback_form_tokens'] ?? null) ? $_SESSION['feedback_form_tokens'] : [];
    $createdAt = $tokens[$token] ?? null;
    unset($tokens[$token]);
    $_SESSION['feedback_form_tokens'] = $tokens;
    if (!is_int($createdAt)) {
        return false;
    }
    $age = time() - $createdAt;
    return $age >= $minimumAgeSeconds && $age <= 7200;
}

function feedback_external_link_count(string $message): int
{
    return preg_match_all('~https?://~iu', $message) ?: 0;
}

function feedback_render_markdown(string $message): string
{
    $message = str_replace(["\r\n", "\r"], "\n", $message);
    $output = [];
    $listItems = [];
    $flushList = static function () use (&$output, &$listItems): void {
        if (!$listItems) {
            return;
        }
        $output[] = '<ul><li>' . implode('</li><li>', $listItems) . '</li></ul>';
        $listItems = [];
    };

    foreach (explode("\n", $message) as $line) {
        if (preg_match('/^\s*-\s+(.+)$/u', $line, $match)) {
            $listItems[] = feedback_render_markdown_inline($match[1]);
            continue;
        }
        $flushList();
        if (trim($line) === '') {
            continue;
        }
        if (preg_match('/^\s*>\s?(.*)$/u', $line, $match)) {
            $output[] = '<blockquote>' . feedback_render_markdown_inline($match[1]) . '</blockquote>';
            continue;
        }
        $output[] = '<p>' . feedback_render_markdown_inline($line) . '</p>';
    }
    $flushList();
    return implode("\n", $output);
}

function feedback_render_markdown_inline(string $text): string
{
    $tokens = [];
    $prefix = '__BBS_' . bin2hex(random_bytes(8)) . '_';
    $store = static function (string $html) use (&$tokens, $prefix): string {
        $key = $prefix . count($tokens) . '__';
        $tokens[$key] = $html;
        return $key;
    };

    $text = preg_replace_callback('/`([^`\n]+)`/u', static function (array $match) use ($store): string {
        return $store('<code>' . e($match[1]) . '</code>');
    }, $text) ?? $text;

    $text = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/u', static function (array $match) use ($store): string {
        $url = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $match[1] . ' (' . $match[2] . ')';
        }
        return $store('<a href="' . e($url) . '" target="_blank" rel="nofollow ugc noopener">' . e($match[1]) . '</a>');
    }, $text) ?? $text;

    $text = e($text);
    $text = preg_replace('/\*\*([^*\n]+)\*\*/u', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '<em>$1</em>', $text) ?? $text;
    return strtr($text, $tokens);
}

function app_base_url(): string
{
    $configured = getenv('SYNTREE_BASE_URL');
    if (is_string($configured) && $configured !== '') {
        return rtrim($configured, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir);
}

function active_action(): string
{
    return is_string($_GET['action'] ?? null) ? $_GET['action'] : 'home';
}
