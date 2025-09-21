<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        // Nous analysons un fichier temporaire spécifique dans RectorAnalyzer
    ])
    ->withSets([
        // Migration vers PHP 8.4
        LevelSetList::UP_TO_PHP_84,

        // Sets de qualité de code
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,

        // Modernisation PHP 8.x
        SetList::PHP_80,
        SetList::PHP_81,
        SetList::PHP_82,
        SetList::PHP_83,
        SetList::PHP_84,
    ])
    ->withDeadCodeLevel(0)     // Désactiver l'analyse de code mort pour l'instant
    ->withParallel()           // Activer le traitement parallèle pour de meilleures performances
    ->withMemoryLimit('1G');   // Limiter l'utilisation mémoire