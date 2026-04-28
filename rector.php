<?php
/**
 * Rector config — Phase 1A.
 *
 * Targets PHP 8.3. Conservative: ONLY the level-set list of PHP version
 * upgrades, no opinionated code-quality / dead-code rules.
 *
 * Paths reflect upstream's relocation:
 *   - public/include/* moved to top-level include/
 *   - public/templates/* moved to top-level templates/
 *
 * Bundled third-party libs and upstream's own custom JSON-RPC client are
 * skipped — automated rewrites of vendored code aren't worth the diff.
 *
 * Usage:
 *   make rector-dry   # show what would change
 *   make rector       # apply changes
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPaths([
        __DIR__ . '/cronjobs',
        __DIR__ . '/scripts',
        __DIR__ . '/include',
        __DIR__ . '/public',
    ])
    ->withSkip([
        // Bundled libraries — don't auto-rewrite vendored code.
        __DIR__ . '/include/smarty',
        __DIR__ . '/include/lib',
        // Compile/cache + ops dirs.
        __DIR__ . '/templates/compile',
        __DIR__ . '/templates/cache',
        __DIR__ . '/tests',
        __DIR__ . '/upgrade',
        __DIR__ . '/vendor',
        __DIR__ . '/logs',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
    ]);
