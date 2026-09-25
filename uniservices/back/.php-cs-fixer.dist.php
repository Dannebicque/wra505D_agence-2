<?php

/*
 * PSR-12, la norme de CLAUDE.md. Ne s'applique qu'aux fichiers qu'une PR modifie (bin/cs-diff) :
 * reformater tout le code du client rendrait illisibles les reprises de son dépôt.
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
