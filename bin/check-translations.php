<?php

declare(strict_types=1);

/**
 * Translation drift checker.
 *
 *     php bin/check-translations.php
 *
 * Compares the English dictionary against every other lang/*.php file and
 * fails with a non-zero exit when one of them disagrees, so a half-finished
 * translation cannot ship blank labels to patients. Suitable for CI or a
 * pre-commit hook.
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

$files = glob($basePath . '/lang/*.php') ?: [];
sort($files);

$problems   = 0;
$suspicious = 0;

printf("English keys: %d\n", count($en));

foreach ($files as $file) {
    $locale = basename($file, '.php');

    if ($locale === 'en') {
        continue;
    }

    $translated = $flatten((array) require $file);

    printf("\n%s keys: %d\n", $locale, count($translated));

    foreach (array_keys(array_diff_key($en, $translated)) as $key) {
        printf("MISSING in %s.php : %s\n", $locale, $key);
        $problems++;
    }

    foreach (array_keys(array_diff_key($translated, $en)) as $key) {
        printf("ORPHAN  in %s.php : %s\n", $locale, $key);
        $problems++;
    }

    foreach ($translated as $key => $value) {
        if (trim($value) === '') {
            printf("EMPTY   in %s.php : %s\n", $locale, $key);
            $problems++;
        }
    }

    // A :placeholder present in one language but not the other means the
    // substitution silently vanishes for those users.
    foreach ($en as $key => $value) {
        if (!isset($translated[$key])) {
            continue;
        }

        preg_match_all('/:([a-z_]+)/', $value, $englishTokens);
        preg_match_all('/:([a-z_]+)/', $translated[$key], $localeTokens);

        sort($englishTokens[1]);
        sort($localeTokens[1]);

        if ($englishTokens[1] !== $localeTokens[1]) {
            printf(
                "PLACEHOLDER mismatch: %s  en[%s]  %s[%s]\n",
                $key,
                implode(',', $englishTokens[1]),
                $locale,
                implode(',', $localeTokens[1]),
            );
            $problems++;
        }
    }

    // Untranslated: a value identical to English that contains Latin letters
    // is often a copy-paste placeholder rather than a real translation.
    // Numeric and symbol-only strings are legitimately identical, and so are
    // the handful of technical placeholders (email/reference formats).
    foreach ($translated as $key => $value) {
        if (!isset($en[$key]) || $value !== $en[$key]) {
            continue;
        }

        if (preg_match('/[A-Za-z]{4,}/', $value) === 1) {
            printf("UNTRANSLATED?     : %s.%s = %s\n", $locale, $key, $value);
            $suspicious++;
        }
    }
}

printf("\n%d problem(s), %d possibly untranslated.\n", $problems, $suspicious);

exit($problems > 0 ? 1 : 0);
