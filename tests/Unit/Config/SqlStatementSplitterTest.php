<?php

namespace Tests\Unit\Config;

use App\Config\SqlStatementSplitter;
use PHPUnit\Framework\TestCase;

final class SqlStatementSplitterTest extends TestCase
{
    public function testSplitsOrdinaryStatements(): void
    {
        self::assertSame(
            ['CREATE TABLE a (id INT)', 'ALTER TABLE a ADD COLUMN name VARCHAR(20)'],
            SqlStatementSplitter::split("CREATE TABLE a (id INT);\nALTER TABLE a ADD COLUMN name VARCHAR(20);\n"),
        );
    }

    public function testDoesNotSplitSemicolonsInsideStringsOrIdentifiers(): void
    {
        $sql = "INSERT INTO a (value) VALUES ('x;y');\nSELECT `semi;column` FROM a;";

        self::assertSame(
            ["INSERT INTO a (value) VALUES ('x;y')", 'SELECT `semi;column` FROM a'],
            SqlStatementSplitter::split($sql),
        );
    }

    public function testCommentsDoNotBreakStatementBoundaries(): void
    {
        $sql = "-- migration comment; still comment\nCREATE TABLE a (id INT);\n/* block; comment */\nCREATE TABLE b (id INT);";
        $statements = SqlStatementSplitter::split($sql);

        self::assertCount(2, $statements);
        self::assertStringContainsString('CREATE TABLE a', $statements[0]);
        self::assertStringContainsString('CREATE TABLE b', $statements[1]);
    }

    public function testEscapedAndDoubledQuotesRemainInsideStatement(): void
    {
        $sql = "INSERT INTO a (value) VALUES ('it\\'s;ok');\nINSERT INTO a (value) VALUES ('it''s;also ok');";

        self::assertCount(2, SqlStatementSplitter::split($sql));
    }
}
