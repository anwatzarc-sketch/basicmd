<?php

declare(strict_types=1);

/**
 * SQL placeholder audit.
 *
 *     php bin/check-sql.php
 *
 * Scans every SQL string literal in src/ for a named placeholder used more
 * than once in the same statement.
 *
 * Why this matters: the PDO connection runs with ATTR_EMULATE_PREPARES set to
 * false, so statements are prepared by MySQL itself rather than interpolated
 * by the driver. Under a real prepared statement a named placeholder may
 * appear exactly once; reusing `:q` across four LIKE columns fails at
 * execute() with SQLSTATE[HY093] "Invalid parameter number".
 *
 * That failure only shows up when the branch containing it actually runs -
 * a search box nobody tried, a rate-limit bucket that had not yet collided -
 * so it is exactly the kind of bug that reaches production. This check finds
 * all of them at once. Use Database::searchClause() for multi-column LIKEs,
 * or give each occurrence its own name.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$root = dirname(__DIR__) . '/src';

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$problems = 0;
$scanned  = 0;

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') {
        continue;
    }

    $source = (string) file_get_contents($file->getPathname());
    $scanned++;

    // Tokenise rather than regex the raw text. A naive string-literal regex
    // treats the apostrophe in a comment like "the clinic's account" as an
    // opening quote and then swallows the following docblock, reporting the
    // `:Money` in an `array{collected:Money}` annotation as a placeholder.
    // token_get_all() is the real PHP lexer, so comments are comments.
    $tokens = @token_get_all($source);

    foreach ($tokens as $token) {
        if (!is_array($token)) {
            continue;
        }

        [$id, $text, $line] = $token;

        // Only plain (non-interpolated) string literals can carry SQL here;
        // the codebase never builds a statement by interpolating a variable.
        if ($id !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $literal = trim($text, "'\"");

        if (preg_match('/\b(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i', $literal) !== 1) {
            continue;
        }

        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $literal, $placeholders);

        foreach (array_count_values($placeholders[1]) as $name => $count) {
            if ($count < 2) {
                continue;
            }

            printf(
                "  REPEATED  :%-12s x%d   %s:%d\n",
                $name,
                $count,
                str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                $line,
            );

            $problems++;
        }
    }
}

printf("\nScanned %d file(s).\n", $scanned);

if ($problems === 0) {
    echo "PASS: no repeated named placeholders.\n";
    exit(0);
}

printf("FAIL: %d repeated placeholder(s) - these throw SQLSTATE[HY093] at runtime.\n", $problems);
exit(1);
