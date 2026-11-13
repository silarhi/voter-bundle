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
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;

/**
 * RemoveUnusedPrivateMethodParameterRector, which keeps the parameters of the #[Grants] methods: an unread $subject
 * still tells the subjects of the rule. Replace the rule with it:
 *
 *     ->withSkip([RemoveUnusedPrivateMethodParameterRector::class])
 *     ->withRules([GrantsAwareRemoveUnusedPrivateMethodParameterRector::class])
 */
final class GrantsAwareRemoveUnusedPrivateMethodParameterRector extends AbstractGrantsAwareRector
{
    public function __construct(
        private readonly RemoveUnusedPrivateMethodParameterRector $rector,
    ) {
    }

    #[Override]
    protected function getDecoratedRule(): RectorInterface
    {
        return $this->rector;
    }
}
