<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'vendor',
        'runtime',
        'gen',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        'braces' => true,
        'array_indentation' => true,
        'indentation_type' => true,
        'statement_indentation' => true,
        'global_namespace_import' => true,
        'ordered_imports' => true,
        'single_import_per_statement' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])->setFinder($finder);
