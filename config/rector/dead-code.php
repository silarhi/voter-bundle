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
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Silarhi\VoterBundle\Rector\GrantsAwareRemoveUnusedPrivateMethodParameterRector;
use Silarhi\VoterBundle\Rector\GrantsAwareRemoveUnusedPrivateMethodRector;

/*
 * Swaps the dead-code rules that would delete the #[Grants] methods, or drop their parameters, for their Grants-aware
 * versions. Skipping the originals is required: the Grants-aware rules run them, which registers them too.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->skip([
        RemoveUnusedPrivateMethodRector::class,
        RemoveUnusedPrivateMethodParameterRector::class,
    ]);

    $rectorConfig->rules([
        GrantsAwareRemoveUnusedPrivateMethodRector::class,
        GrantsAwareRemoveUnusedPrivateMethodParameterRector::class,
    ]);
};
