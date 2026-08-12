<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/db.php';

function counter_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

if (!function_exists('generation_count') || !function_exists('increment_generation_count')) {
    fwrite(STDERR, "FAIL: generation counter functions are missing.\n");
    exit(1);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
init_schema($pdo);

counter_assert_same(0, generation_count($pdo), 'A new site starts with zero generated trees.');
counter_assert_same(1, increment_generation_count($pdo), 'The first completed download increments the counter to one.');
counter_assert_same(2, increment_generation_count($pdo), 'Every later completed download increments exactly once.');
counter_assert_same(2, generation_count($pdo), 'The generated tree count persists in site settings.');

$source = file_get_contents(dirname(__DIR__) . '/index.php');
$app = file_get_contents(dirname(__DIR__) . '/app.js');
if ($source === false || $app === false) {
    fwrite(STDERR, "FAIL: could not inspect editor sources.\n");
    exit(1);
}

foreach ([
    "const SYNTREE_VERSION = '0.2.2'",
    'id="generationCounter"',
    "action=record_generation",
    "t('latest_update_5')",
    'class="landing-syntax-details"',
    "['(...)'",
] as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "FAIL: missing release or counter contract: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($app, 'recordGenerationDownload();')) {
    fwrite(STDERR, "FAIL: successful export downloads must record one generated tree.\n");
    exit(1);
}

echo "generation counter and release notes tests passed\n";
