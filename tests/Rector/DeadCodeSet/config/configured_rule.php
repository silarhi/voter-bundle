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
use Silarhi\VoterBundle\Rector\Set\VoterSetList;

// as an application configures it: the dead-code rules, then the set swapping them
return RectorConfig::configure()
    ->withRules([RemoveUnusedPrivateMethodRector::class, RemoveUnusedPrivateMethodParameterRector::class])
    ->withSets([VoterSetList::DEAD_CODE]);
