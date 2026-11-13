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
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Rules\Methods\AlwaysUsedMethodExtension;
use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

/**
 * The voter calls its #[Grants] methods: PHPStan must not report the private ones as unused.
 *
 * Registered by extension.neon, which phpstan/extension-installer includes.
 */
final class GrantsMethodExtension implements AlwaysUsedMethodExtension
{
    #[Override]
    public function isAlwaysUsed(ExtendedMethodReflection $methodReflection): bool
    {
        $class = $methodReflection->getDeclaringClass();
        if (!$class->is(AbstractRuleVoter::class)) {
            return false;
        }

        $native = $class->getNativeReflection();
        $name = $methodReflection->getName();

        return $native->hasMethod($name) && [] !== $native->getMethod($name)->getAttributes(Grants::class);
    }
}
