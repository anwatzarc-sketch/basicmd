<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Database;

/**
 * Splits a .sql file into individual statements on top-level semicolons.
 *
 * A naive `explode(';', $sql)` breaks the moment a string literal, a quoted
 * identifier, or a comment contains a semicolon or an apostrophe of its own -
 * exactly the class of bug bin/check-sql.php hit and had to be rewritten
 * around (its docblock tells the same story for a different regex). This is
 * a small character-scanning state machine rather than a regex, for the same
 * reason: only a scanner that actually tracks "am I inside a string/comment
 * right now" can be trusted not to false-split or false-join.
 *
 * Deliberately NOT supported: a `DELIMITER` pragma and multi-statement
 * routine bodies (stored procedures/triggers with an embedded `; ... END;`).
 * Phase II's migrations do not need them - GENERATED ALWAYS AS (...) STORED
 * covers the one place a trigger might otherwise have been reached for - and
 * leaving that support out keeps this scanner small enough to read in one
 * sitting and be confident about.
 */
final class SqlStatementSplitter
{
    /**
     * @return list<string> non-empty, trimmed statements, in source order
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $current    = '';

        $length = strlen($sql);
        $i      = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // Line comment: -- or # runs to end of line.
            if (($char === '-' && $next === '-') || $char === '#') {
                $eol = strpos($sql, "\n", $i);
                $i   = $eol === false ? $length : $eol + 1;

                continue;
            }

            // Block comment: /* ... */ (does not nest in SQL).
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i   = $end === false ? $length : $end + 2;

                continue;
            }

            // Quoted string or identifier: ' " ` - copy verbatim through to
            // its unescaped closing quote, including a doubled-quote escape
            // ('' inside a '...' string) and a backslash escape.
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote  = $char;
                $start  = $i;
                $i++;

                while ($i < $length) {
                    if ($sql[$i] === '\\' && $quote !== '`') {
                        // Backslash-escapes the next character (MySQL/MariaDB
                        // default sql_mode); skip both.
                        $i += 2;

                        continue;
                    }

                    if ($sql[$i] === $quote) {
                        if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                            // Doubled-quote escape: '' or "" or ``.
                            $i += 2;

                            continue;
                        }

                        $i++; // consume the closing quote

                        break;
                    }

                    $i++;
                }

                $current .= substr($sql, $start, $i - $start);

                continue;
            }

            // Top-level statement terminator.
            if ($char === ';') {
                $trimmed = trim($current);

                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }

                $current = '';
                $i++;

                continue;
            }

            $current .= $char;
            $i++;
        }

        $trimmed = trim($current);

        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
