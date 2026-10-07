<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Concat\DirnameDirConcatStringToDirectStringPathRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

// tests/e2e is left out: its WordPress function stubs would stand in for the real signatures
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/gravity-forms-multi-instance-sync.php',
        __DIR__ . '/src',
        __DIR__ . '/templates',
        __DIR__ . '/tests/bootstrap.php',
        __DIR__ . '/tests/TestPlugin.php',
    ])
    ->withSkip([
        // Hooks added as closures can't be removed with remove_filter()
        ArrayToFirstClassCallableRector::class,
        DirnameDirConcatStringToDirectStringPathRector::class,
    ])
    ->withPHPStanConfigs([__DIR__ . '/vendor/szepeviktor/phpstan-wordpress/extension.neon'])
    ->withPhpSets(php84: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
    ])
    ->withImportNames(removeUnusedImports: true);
