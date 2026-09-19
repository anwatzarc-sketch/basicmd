<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Unit\Infrastructure\Database;

use MediCareMini\Infrastructure\Database\SqlStatementSplitter;
use PHPUnit\Framework\TestCase;

final class SqlStatementSplitterTest extends TestCase
{
    public function test_splits_two_plain_statements(): void
    {
        $sql = "CREATE TABLE a (id INT);\nCREATE TABLE b (id INT);";

        self::assertSame(
            ['CREATE TABLE a (id INT)', 'CREATE TABLE b (id INT)'],
            SqlStatementSplitter::split($sql),
        );
    }

    public function test_semicolon_inside_single_quoted_string_does_not_split(): void
    {
        $sql = "INSERT INTO t (note) VALUES ('a; b; c');";

        self::assertSame(
            ["INSERT INTO t (note) VALUES ('a; b; c')"],
            SqlStatementSplitter::split($sql),
        );
    }

    public function test_apostrophe_inside_a_comment_does_not_break_parsing(): void
    {
        // This exact shape (an apostrophe in a -- comment) is what broke the
        // first regex version of bin/check-sql.php.
        $sql = "-- Ethiopian names like O'Brien or D'Angelo need quoting\n"
            . "CREATE TABLE t (id INT);";

        self::assertSame(['CREATE TABLE t (id INT)'], SqlStatementSplitter::split($sql));
    }

    public function test_block_comment_containing_a_semicolon_is_ignored(): void
    {
        $sql = "/* setup; do not touch; */\nCREATE TABLE t (id INT);";

        self::assertSame(['CREATE TABLE t (id INT)'], SqlStatementSplitter::split($sql));
    }

    public function test_doubled_single_quote_escape_inside_a_string(): void
    {
        // 'It''s fine' is the SQL-standard way to embed an apostrophe.
        $sql = "INSERT INTO t (note) VALUES ('It''s fine; really');";

        self::assertSame(
            ["INSERT INTO t (note) VALUES ('It''s fine; really')"],
            SqlStatementSplitter::split($sql),
        );
    }

    public function test_backtick_quoted_identifier_containing_a_semicolon(): void
    {
        $sql = 'CREATE TABLE `weird;name` (id INT);';

        self::assertSame(['CREATE TABLE `weird;name` (id INT)'], SqlStatementSplitter::split($sql));
    }

    public function test_backslash_escaped_quote_inside_a_string(): void
    {
        $sql = "INSERT INTO t (note) VALUES ('a \\' b; c');";

        self::assertSame(
            ["INSERT INTO t (note) VALUES ('a \\' b; c')"],
            SqlStatementSplitter::split($sql),
        );
    }

    public function test_double_quoted_string_with_semicolon(): void
    {
        $sql = 'INSERT INTO t (note) VALUES ("a; b");';

        self::assertSame(['INSERT INTO t (note) VALUES ("a; b")'], SqlStatementSplitter::split($sql));
    }

    public function test_empty_statements_between_semicolons_are_dropped(): void
    {
        $sql = "CREATE TABLE a (id INT);;\n\n;CREATE TABLE b (id INT);";

        self::assertSame(
            ['CREATE TABLE a (id INT)', 'CREATE TABLE b (id INT)'],
            SqlStatementSplitter::split($sql),
        );
    }

    public function test_trailing_statement_without_final_semicolon_is_included(): void
    {
        $sql = 'CREATE TABLE a (id INT)';

        self::assertSame(['CREATE TABLE a (id INT)'], SqlStatementSplitter::split($sql));
    }

    public function test_empty_input_yields_no_statements(): void
    {
        self::assertSame([], SqlStatementSplitter::split(''));
        self::assertSame([], SqlStatementSplitter::split("   \n\t  "));
        self::assertSame([], SqlStatementSplitter::split("-- just a comment\n"));
    }

    public function test_realistic_migration_file_with_mixed_comment_styles(): void
    {
        $sql = <<<SQL
            -- =====================================================================
            --  Sample migration - it's got an apostrophe right there
            -- =====================================================================
            CREATE TABLE `patients` (
                `id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `pid` VARCHAR(32)  NOT NULL, -- e.g. "PID-2026-00001"
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB; # trailing hash comment

            /* second statement */
            CREATE TABLE `patient_accounts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            SQL;

        $statements = SqlStatementSplitter::split($sql);

        self::assertCount(2, $statements);
        self::assertStringContainsString('CREATE TABLE `patients`', $statements[0]);
        self::assertStringNotContainsString('apostrophe', $statements[0]);
        self::assertStringContainsString('CREATE TABLE `patient_accounts`', $statements[1]);
    }
}
