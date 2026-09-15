<?php

declare(strict_types=1);

/**
 * Tailwind @apply validator.
 *
 *     php bin/check-css.php
 *
 * Extracts every utility referenced by an `@apply` in resources/css/app.css and
 * reports the ones Tailwind cannot generate.
 *
 * Why this exists: `@apply some-class-that-does-not-exist` is a hard build
 * failure, and the compiler stops at the FIRST one it meets. On a large
 * stylesheet that turns into a slow build-fix-rebuild loop, one class at a
 * time. This lists them all in a single pass.
 *
 * It works by writing every token into a throwaway file, letting Tailwind scan
 * it as content (where an unknown class is silently skipped rather than fatal),
 * and diffing what came back against what went in.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$root  = dirname(__DIR__);
$input = $root . '/resources/css/app.css';

if (!is_file($input)) {
    exit("resources/css/app.css not found.\n");
}

$css = (string) file_get_contents($input);

// ---------------------------------------------------------------------
//  Collect the tokens
// ---------------------------------------------------------------------

// Blank out comments first. Prose that merely MENTIONS @apply is not a real
// directive, and because the capture runs to the next semicolon it would
// otherwise swallow the whole comment and report every English word in it as
// a missing utility - 47 of them, on a stylesheet that builds cleanly.
// Newlines are preserved so the reported line numbers stay accurate.
$scannable = preg_replace_callback(
    '#/\*.*?\*/#s',
    static fn (array $m): string => preg_replace('/[^
]/', ' ', $m[0]) ?? '',
    $css,
) ?? $css;

preg_match_all('/@apply\s+([^;{}]+);/s', $scannable, $matches);

$tokens = [];

foreach ($matches[1] as $block) {
    foreach (preg_split('/\s+/', trim($block)) ?: [] as $token) {
        $token = trim($token);

        if ($token === '' || $token === '!important') {
            continue;
        }

        $tokens[$token] = true;
    }
}

$tokens = array_keys($tokens);

printf("Found %d distinct utilities referenced by @apply.\n\n", count($tokens));

// ---------------------------------------------------------------------
//  Ask Tailwind which ones it can build
// ---------------------------------------------------------------------

$tmpDir = $root . '/storage/cache/csscheck';

if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

// Content file: the tokens as plain class names. Tailwind scans this and
// silently ignores anything it does not recognise.
file_put_contents($tmpDir . '/probe.html', '<div class="' . implode(' ', $tokens) . '"></div>');

file_put_contents($tmpDir . '/probe.css', "@tailwind utilities;\n");

// A config that reuses the project theme but scans only the probe file, and
// with no safelist - a safelisted pattern would mask a genuinely missing class.
// No `presets: []` here. Setting it strips Tailwind's own default theme, so
// even `text-sm` and `ring-2` stop generating and every token reads as missing.
$config = <<<'JS'
const base = require(process.argv[1] + '/tailwind.config.js');

module.exports = {
  content: [process.argv[1] + '/storage/cache/csscheck/probe.html'],
  darkMode: base.darkMode,
  theme: base.theme,
  plugins: base.plugins,
  safelist: [],
};
JS;

file_put_contents(
    $tmpDir . '/tailwind.probe.js',
    str_replace('process.argv[1]', json_encode(str_replace('\\', '/', $root)), $config),
);

$command = sprintf(
    'npx tailwindcss -c %s -i %s -o %s 2>&1',
    escapeshellarg($tmpDir . '/tailwind.probe.js'),
    escapeshellarg($tmpDir . '/probe.css'),
    escapeshellarg($tmpDir . '/probe.out.css'),
);

exec($command, $output, $status);

if (!is_file($tmpDir . '/probe.out.css')) {
    echo "Tailwind probe failed:\n" . implode("\n", $output) . "\n";
    exit(1);
}

$generated = (string) file_get_contents($tmpDir . '/probe.out.css');

// ---------------------------------------------------------------------
//  Diff
// ---------------------------------------------------------------------

// Classes app.css defines itself inside @layer components. These are valid
// @apply targets but are not Tailwind utilities, so the probe will never
// generate them and they must not be reported as missing.
preg_match_all('/^\s*\.([a-zA-Z][\w-]*)\s*(?:,|\{)/m', $css, $ownClasses);
$definedLocally = array_flip($ownClasses[1]);

$missing = [];

foreach ($tokens as $token) {
    $bare = ltrim($token, '!');

    // Arbitrary values (bg-[length:200%_auto]) always compile.
    if (str_contains($bare, '[')) {
        continue;
    }

    // A class defined in this same stylesheet.
    $base = substr($bare, (int) strrpos(':' . $bare, ':'));

    if (isset($definedLocally[$bare]) || isset($definedLocally[$base])) {
        continue;
    }

    // @tailwindcss/typography modifiers (prose-slate, prose-invert,
    // prose-headings:*) are only emitted alongside `prose` itself, so the
    // isolated probe never generates them even though the real build does.
    // Reporting them would be a false alarm on every run.
    if (str_starts_with($base, 'prose')) {
        continue;
    }

    // Match the FULL token as Tailwind emits it: variants are part of the
    // class name, so `dark:text-slate-300` becomes `.dark\:text-slate-300`.
    // Looking for the stripped `.text-slate-300` finds nothing, because the
    // probe only ever requested the variant form.
    $escaped = str_replace(
        [':', '.', '/', '%', '[', ']', '(', ')', ','],
        ['\\:', '\\.', '\\/', '\\%', '\\[', '\\]', '\\(', '\\)', '\\,'],
        $bare,
    );

    if (!str_contains($generated, '.' . $escaped)) {
        $missing[] = $token;
    }
}

if ($missing === []) {
    echo "PASS: every @apply utility resolves.\n";
    exit(0);
}

echo "MISSING - these will fail the build:\n\n";

foreach ($missing as $token) {
    // Find the line it is used on, so the fix is one jump away.
    $lines = [];

    foreach (explode("\n", $css) as $number => $line) {
        if (str_contains($line, $token)) {
            $lines[] = $number + 1;
        }
    }

    printf("  %-28s line(s) %s\n", $token, implode(', ', array_slice($lines, 0, 4)) ?: '?');
}

printf("\n%d missing utility/utilities. Add them to tailwind.config.js or use an existing step.\n", count($missing));

exit(1);
