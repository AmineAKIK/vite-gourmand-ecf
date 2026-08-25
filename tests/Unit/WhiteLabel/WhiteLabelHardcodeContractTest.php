<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WhiteLabelHardcodeContractTest extends TestCase
{
    /** @dataProvider forbiddenRuntimeMarkers */
    public function testHistoricalBrandMarkersAreAbsentFromRuntime(string $marker): void
    {
        $root = dirname(__DIR__, 3);
        $files = $this->runtimeFiles($root);
        $matches = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            if (stripos($contents, $marker) !== false) {
                $matches[] = substr($file, strlen($root) + 1);
            }
        }

        self::assertSame([], $matches, sprintf(
            'Forbidden white-label marker "%s" found in: %s',
            $marker,
            implode(', ', $matches),
        ));
    }

    /** @return iterable<string,array{string}> */
    public static function forbiddenRuntimeMarkers(): iterable
    {
        foreach ([
            'legacy-theme',
            '--vg-',
            'text-vg',
            'bg-vg',
            'bg-creme',
            'btn-vg',
            'VITE & GOURMAND',
            '#8B1A2B',
            '#6B1221',
            '#D4A843',
            '#E8C46A',
            '#FDF6EC',
            'Playfair Display',
        ] as $marker) {
            yield $marker => [$marker];
        }
    }

    /** @return list<string> */
    private function runtimeFiles(string $root): array
    {
        $roots = [
            $root . '/public/css',
            $root . '/public/js',
            $root . '/src/Config',
            $root . '/src/Controllers',
            $root . '/src/Views',
            $root . '/src/helpers.php',
        ];
        $files = [];

        foreach ($roots as $path) {
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($iterator as $entry) {
                if (!$entry->isFile()) {
                    continue;
                }
                $extension = strtolower($entry->getExtension());
                if (!in_array($extension, ['php', 'css', 'js'], true)) {
                    continue;
                }
                $files[] = $entry->getPathname();
            }
        }

        sort($files);
        return $files;
    }
}
