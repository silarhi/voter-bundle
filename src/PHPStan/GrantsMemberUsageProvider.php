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

namespace Silarhi\VoterBundle\PHPStan;

use Override;
use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

/**
 * The voter calls its #[Grants] methods: shipmonk/dead-code-detector must not report them as dead.
 *
 * Registered by shipmonk.neon, which applications using the detector include: extension.neon cannot, since this class
 * needs the detector.
 */
final class GrantsMemberUsageProvider extends ReflectionBasedMemberUsageProvider
{
    #[Override]
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        if ([] === $method->getAttributes(Grants::class) || !$method->getDeclaringClass()->isSubclassOf(AbstractRuleVoter::class)) {
            return null;
        }

        return VirtualUsageData::withNote('Called by AbstractRuleVoter for its #[Grants]');
    }
}
