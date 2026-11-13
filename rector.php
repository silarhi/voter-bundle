<?php

declare(strict_types=1);

/*
 * This file is part of the Voter Bundle package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Silarhi\VoterBundle\Rector\Set\VoterSetList;

return RectorConfig::configure()
    ->withCache(__DIR__ . '/var/tools/rector')
    ->withPaths([
        'config/rector',
        'src',
        'tests',
    ])
    ->withSkip([
        // Symfony 8.1 moves the bundle classes to DependencyInjection\Kernel: the bundle still supports 7.4
        RenameClassRector::class,
        // read through reflection by the rule tests, not code to modernize
        __DIR__ . '/tests/PHPStan/Data',
        // the voters RuleLoaderTest builds are invalid on purpose: no return type, unused parameters…
        __DIR__ . '/tests/Rule/RuleLoaderTest.php',
        // Symfony 7.4 still declares eraseCredentials() on UserInterface
        RemoveEmptyClassMethodRector::class => [__DIR__ . '/tests/Fixtures/Model/User.php'],
    ])
    ->withPhpSets(php82: true)
    ->withComposerBased(
        symfony: true,
    )
    // the dead-code set would delete the #[Grants] methods of the fixtures: the bundle's own set keeps them
    ->withSets([VoterSetList::DEAD_CODE])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
