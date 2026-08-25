<?php

namespace App\Config;

use PDO;
use RuntimeException;

/**
 * Expands legacy MySQL-incompatible ALTER TABLE statements without changing
 * the historical migration files or their checksums.
 *
 * Some historical migrations use `ADD COLUMN IF NOT EXISTS` inside a compound
 * ALTER TABLE. MySQL does not accept that syntax. The adapter decomposes only
 * those statements into single DDL operations and proves idempotence from
 * information_schema before execution.
 */
final class LegacyAlterTableCompatibility
{
    /** @return list<string> */
    public static function expand(PDO $db, string $statement): array
    {
        if (stripos($statement, 'ADD COLUMN IF NOT EXISTS') === false) {
            return [$statement];
        }

        $parsed = self::parseAlterTable($statement);
        if ($parsed === null) {
            throw new RuntimeException('ALTER TABLE legacy non analysable.');
        }

        [$tableToken, $tableName, $actions] = $parsed;
        $expanded = [];

        foreach ($actions as $action) {
            if (preg_match('/^ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s+(.+)$/is', $action, $matches)) {
                $column = $matches[1];
                if (!self::columnExists($db, $tableName, $column)) {
                    $expanded[] = sprintf('ALTER TABLE %s ADD COLUMN `%s` %s', $tableToken, $column, trim($matches[2]));
                }
                continue;
            }

            if (preg_match('/^ADD\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?([A-Za-z0-9_]+)`?\b/is', $action, $matches)) {
                if (!self::indexExists($db, $tableName, $matches[1])) {
                    $expanded[] = 'ALTER TABLE ' . $tableToken . ' ' . $action;
                }
                continue;
            }

            if (preg_match('/^ADD\s+CONSTRAINT\s+`?([A-Za-z0-9_]+)`?\b/is', $action, $matches)) {
                if (!self::constraintExists($db, $tableName, $matches[1])) {
                    $expanded[] = 'ALTER TABLE ' . $tableToken . ' ' . $action;
                }
                continue;
            }

            $expanded[] = 'ALTER TABLE ' . $tableToken . ' ' . $action;
        }

        return $expanded;
    }

    /** @return array{0:string,1:string,2:list<string>}|null */
    private static function parseAlterTable(string $statement): ?array
    {
        if (!preg_match('/^\s*ALTER\s+TABLE\s+(`?([A-Za-z0-9_]+)`?)\s+(.+)\s*$/is', $statement, $matches)) {
            return null;
        }

        return [$matches[1], $matches[2], self::splitTopLevelCommaList($matches[3])];
    }

    /** @return list<string> */
    private static function splitTopLevelCommaList(string $input): array
    {
        $items = [];
        $buffer = '';
        $quote = null;
        $depth = 0;
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            $next = $i + 1 < $length ? $input[$i + 1] : '';

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`' && $next !== '') {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $item = trim($buffer);
                if ($item !== '') {
                    $items[] = $item;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $item = trim($buffer);
        if ($item !== '') {
            $items[] = $item;
        }

        return $items;
    }

    private static function columnExists(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function indexExists(PDO $db, string $table, string $index): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        );
        $stmt->execute([$table, $index]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function constraintExists(PDO $db, string $table, string $constraint): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
        );
        $stmt->execute([$table, $constraint]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
