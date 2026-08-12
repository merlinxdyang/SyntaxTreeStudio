<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/db.php';

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$options = language_options();
assert_same('Español', $options['es'] ?? null, 'Spanish must be available as a language option.');
assert_same(['zh', 'en', 'es'], default_language_codes(), 'Chinese, English, and Spanish must be enabled by default.');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
init_schema($pdo);
$seeded = $pdo->query("SELECT value FROM site_settings WHERE key = 'enabled_languages'")->fetchColumn();
assert_same(['zh', 'en', 'es'], json_decode((string) $seeded, true), 'A new database must seed the three default languages.');

$source = file_get_contents(dirname(__DIR__) . '/index.php');
preg_match("/'en'\\s*=>\\s*\\[(.*?)\\n\\s*\\],\\n\\s*'zh'/s", $source, $englishMatch);
preg_match("/'es'\\s*=>\\s*\\[(.*?)\\n\\s*\\],\\n\\s*'ja'/s", $source, $spanishMatch);
preg_match_all("/'([a-z0-9_]+)'\\s*=>/", $englishMatch[1] ?? '', $englishKeys);
preg_match_all("/'([a-z0-9_]+)'\\s*=>/", $spanishMatch[1] ?? '', $spanishKeys);
assert_same($englishKeys[1], $spanishKeys[1], 'Spanish must translate every public interface key.');

$runtimeLabels = [
    'copied' => 'copied',
    'saving' => 'saving',
    'saved' => 'saved',
    'annotationColor' => 'annotation_color',
    'showMovementOne' => 'show_movement_one',
    'movementStyle' => 'movement_style',
    'solid' => 'solid',
    'dashed' => 'dashed',
    'emptyMovementPosition' => 'empty_movement_position',
    'hideBranch' => 'hide_branch',
    'restoreBranch' => 'restore_branch',
    'foundStats' => 'found_stats',
];
foreach ($runtimeLabels as $jsLabel => $key) {
    if (!in_array($key, $spanishKeys[1], true)) {
        fwrite(STDERR, "Spanish must translate the dynamic interface key: {$key}." . PHP_EOL);
        exit(1);
    }
    if (!str_contains($source, "'{$jsLabel}' => t('{$key}')")) {
        fwrite(STDERR, "The JavaScript label for {$key} must use the translation dictionary." . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($source, "if (\$lang === 'es')")) {
    fwrite(STDERR, 'Spanish help content must be localized.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "language settings tests passed\n");
