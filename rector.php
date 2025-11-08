<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/lib',
        __DIR__.'/tests',
    ])
    ->withPreparedSets(codeQuality: true, codingStyle: true, typeDeclarations: true)
    ->withImportNames(removeUnusedImports: true)
    ->withPhpSets(php84: true)
;
