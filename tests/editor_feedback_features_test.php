<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$index = file_get_contents($root . '/index.php');
$app = file_get_contents($root . '/app.js');

if ($index === false || $app === false) {
    fwrite(STDERR, "Could not read editor source files.\n");
    exit(1);
}

function assert_contains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }
}

assert_contains($index, 'id="undoInput"', 'The input toolbar must expose undo.');
assert_contains($index, 'id="redoInput"', 'The input toolbar must expose redo.');
assert_contains($index, 'id="annotationColorPalette"', 'Annotations must expose a color palette.');
assert_contains($index, 'id="hiddenBranchesPanel"', 'Hidden branches must remain recoverable from the controls.');
assert_contains($index, 'id="downloadForestLatex"', 'Semantic Forest export must have its own button.');
assert_contains($index, 'id="downloadTikzLatex"', 'Visual TikZ export must have its own button.');
assert_contains($index, 'T0|\\[+PST\\]', 'The guide must show escaped square brackets in multiline labels.');

assert_contains($app, 'let movementStyles = {}', 'Movement styles must be stored per link.');
assert_contains($app, 'let hiddenBranches = {}', 'Hidden branches must have persistent editor state.');
assert_contains($app, 'function renderBranchHideMenu', 'Selecting a branch must expose a hide action.');
assert_contains($app, 'function renderHiddenBranchControls', 'Hidden branches must have restore controls.');
assert_contains($app, 'function toVisualTikzLatex', 'Visual TikZ export must use the edited geometry.');
assert_contains($app, '\\useforestlibrary{linguistics}', 'Forest export must load the library required by roof nodes.');
assert_contains($app, '\\usepackage[fontset=fandol]{ctex}', 'XeLaTeX exports must preserve CJK annotations and labels.');
assert_contains($app, 'normalizeEmptyNodeLabel', 'The parser must normalize [] and indexed empty positions.');
assert_contains($app, 'canvasWrap.addEventListener("wheel"', 'The preview must support trackpad pinch zoom.');

echo "editor feedback feature contracts passed\n";
