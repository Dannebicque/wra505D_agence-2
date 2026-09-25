<?php

use Rector\Config\RectorConfig;
use Rector\Doctrine\Bundle230\Rector\Class_\AddAnnotationToRepositoryRector;

/*
 * Montées de version uniquement : les sets suivent les versions installées (composer.lock).
 * Chaque passage est relu, puis `make cs` remet le résultat au format PSR-12.
 */
return RectorConfig::configure()
    ->withPaths(array_merge(
        [__DIR__.'/src', __DIR__.'/tests'],
        glob(__DIR__.'/../packages/*/src', GLOB_ONLYDIR) ?: [],
    ))
    ->withComposerBased(doctrine: true, phpunit: true, symfony: true)
    // Typage des repositories : traité avec PHPStan (E15), pas avec les montées de version.
    ->withSkip([AddAnnotationToRepositoryRector::class])
    ->withSymfonyContainerXml(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.xml')
;
