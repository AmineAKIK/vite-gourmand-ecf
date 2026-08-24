<?php

namespace App\Config;

final class SqlStatementSplitter
{
    /**
     * Split SQL on semicolons that are outside strings and quoted identifiers.
     * Ordinary comments are discarded so statement classification remains stable.
     *
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= "\n";
                }
                continue;
            }

            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $i++;
                    $blockComment = false;
                    $buffer .= ' ';
                }
                continue;
            }

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

            if (($char === '-' && $next === '-') || $char === '#') {
                $lineComment = true;
                if ($char === '-') {
                    $i++;
                }
                continue;
            }

            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
