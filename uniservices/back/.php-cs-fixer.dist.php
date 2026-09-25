<?php

/*
 * PSR-12, la norme de CLAUDE.md, sur tout le code. Les reprises du dépôt du client passent par
 * bin/upstream-diff, qui applique cette même configuration aux deux côtés de son diff.
 */
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->in(glob(__DIR__.'/../packages/*/src', GLOB_ONLYDIR) ?: [])
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder)
;
