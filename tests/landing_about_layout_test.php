<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$index = file_get_contents($root . '/index.php');
$style = file_get_contents($root . '/style.css');
$script = @file_get_contents($root . '/landing.js');

if ($index === false || $style === false) {
    fwrite(STDERR, "FAIL: could not read landing-page sources.\n");
    exit(1);
}

function landing_assert_contains(string $source, string $needle, string $message): void
{
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }
}

function landing_assert_not_contains(string $source, string $needle, string $message): void
{
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }
}

landing_assert_contains($style, 'max-height: 360px;', 'Latest Update must have a fixed desktop maximum height.');
landing_assert_contains($style, 'overflow-y: auto;', 'Overflowing release notes must scroll inside Latest Update.');
landing_assert_contains($index, 'class="landing-about', 'The shared compact About section must be rendered.');
landing_assert_contains($index, "t('about_maker')", 'The compact About section must show the developer.');
landing_assert_contains($index, "t('about_version')", 'The compact About section must show the version.');
landing_assert_contains($index, "t('about_coffee_title')", 'The compact About section must retain Buy me a coffee.');
landing_assert_contains($index, "render_support_footer('landing');", 'The home page must render the shared support footer.');
landing_assert_contains($index, "render_support_footer('workspace');", 'The Workspace must render the shared support footer.');
landing_assert_contains($style, '.workspace-support {', 'The Workspace support footer needs its own compact layout.');
landing_assert_contains($style, 'min-height: 64px;', 'The Workspace support footer must be shorter than the home-page footer.');

$stepsPosition = strpos($index, '<section id="how" class="landing-band">');
$aboutPosition = strpos($index, '<section class="landing-about');
if ($stepsPosition === false || $aboutPosition === false || $aboutPosition < $stepsPosition) {
    fwrite(STDERR, "FAIL: the compact About section must be the final section after the three-step band.\n");
    exit(1);
}
landing_assert_contains($style, 'font-size: 18px;', 'Footer identity values must use compact typography.');
landing_assert_contains($style, 'font-size: 20px;', 'The Buy me a coffee heading must use compact typography.');

landing_assert_not_contains($index, "function render_about(): void", 'The standalone About renderer must be removed.');
landing_assert_not_contains($index, "page_url('about')", 'No navigation should still link to a standalone About page.');
landing_assert_contains($index, "if (\$action === 'about')", 'Old About URLs must be handled explicitly.');
landing_assert_contains($index, 'redirect(page_url());', 'Old About URLs must redirect to the home page.');

landing_assert_contains($index, 'data-alipay-open', 'Chinese visitors need an Alipay button.');
landing_assert_contains($index, 'id="alipayDialog"', 'The Alipay QR code must live in a dialog.');
landing_assert_contains($index, 'assets/alipay-coffee.jpeg', 'The existing Alipay QR asset must be retained.');
landing_assert_contains($index, 'landing.js?v=', 'The landing interaction script must be loaded on the home page.');

if ($script === false) {
    fwrite(STDERR, "FAIL: landing.js is missing.\n");
    exit(1);
}
landing_assert_contains($script, 'showModal()', 'The Alipay button must open a modal dialog.');
landing_assert_contains($script, 'dialog.close()', 'The Alipay dialog must be dismissible.');
landing_assert_contains($script, 'event.clientX', 'Clicking outside the QR card must close the dialog.');

echo "landing About layout tests passed\n";
