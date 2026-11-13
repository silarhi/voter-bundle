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

namespace Silarhi\VoterBundle\Rector;

use Override;
use Rector\Contract\Rector\RectorInterface;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;

/**
 * RemoveUnusedPrivateMethodRector, which keeps the #[Grants] methods. Replace the rule with it:
 *
 *     ->withSkip([RemoveUnusedPrivateMethodRector::class])
 *     ->withRules([GrantsAwareRemoveUnusedPrivateMethodRector::class])
 */
final class GrantsAwareRemoveUnusedPrivateMethodRector extends AbstractGrantsAwareRector
{
    public function __construct(
        private readonly RemoveUnusedPrivateMethodRector $rector,
    ) {
    }

    #[Override]
    protected function getDecoratedRule(): RectorInterface
    {
        return $this->rector;
    }
}
