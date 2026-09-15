<?php

declare(strict_types=1);

/**
 * Translation drift checker.
 *
 *     php bin/check-translations.php
 *
 * Fails with a non-zero exit when the English and Amharic dictionaries
 * disagree, so a half-finished translation cannot ship blank labels to
 * patients. Suitable for CI or a pre-commit hook.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);

$flatten = static function (array $items, string $prefix = '') use (&$flatten): array {
    $flat = [];

    foreach ($items as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

        if (is_array($value)) {
            $flat += $flatten($value, $path);
        } else {
            $flat[$path] = (string) $value;
        }
    }

    return $flat;
};

$en = $flatten((array) require $basePath . '/lang/en.php');
$am = $flatten((array) require $basePath . '/lang/am.php');

$problems = 0;

printf("English keys: %d\nAmharic keys: %d\n\n", count($en), count($am));

foreach (array_keys(array_diff_key($en, $am)) as $key) {
    printf("MISSING in am.php : %s\n", $key);
    $problems++;
}

foreach (array_keys(array_diff_key($am, $en)) as $key) {
    printf("ORPHAN  in am.php : %s\n", $key);
    $problems++;
}

foreach ($am as $key => $value) {
    if (trim($value) === '') {
        printf("EMPTY   in am.php : %s\n", $key);
        $problems++;
    }
}

// A :placeholder present in one language but not the other means the
// substitution silently vanishes for those users.
foreach ($en as $key => $value) {
    if (!isset($am[$key])) {
        continue;
    }

    preg_match_all('/:([a-z_]+)/', $value, $englishTokens);
    preg_match_all('/:([a-z_]+)/', $am[$key], $amharicTokens);

    sort($englishTokens[1]);
    sort($amharicTokens[1]);

    if ($englishTokens[1] !== $amharicTokens[1]) {
        printf(
            "PLACEHOLDER mismatch: %s  en[%s]  am[%s]\n",
            $key,
            implode(',', $englishTokens[1]),
            implode(',', $amharicTokens[1]),
        );
        $problems++;
    }
}

// Untranslated Amharic: a value identical to English that contains Latin
// letters is almost always a copy-paste placeholder rather than a real
// translation. Numeric and symbol-only strings are legitimately identical.
$suspicious = 0;

foreach ($am as $key => $value) {
    if (!isset($en[$key]) || $value !== $en[$key]) {
        continue;
    }

    if (preg_match('/[A-Za-z]{4,}/', $value) === 1) {
        printf("UNTRANSLATED?     : %s = %s\n", $key, $value);
        $suspicious++;
    }
}

printf("\n%d problem(s), %d possibly untranslated.\n", $problems, $suspicious);

exit($problems > 0 ? 1 : 0);
