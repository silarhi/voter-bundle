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

namespace Silarhi\VoterBundle\Tests\Fixtures\Voter;

use Override;
use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Attribute\ProvidesArgument;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Profile;
use Silarhi\VoterBundle\Tests\Fixtures\Model\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The second layer: denies a user without a profile, adds the Profile; its protected rule is inherited.
 */
#[ProvidesArgument(Profile::class)]
abstract class AbstractProfileVoter extends AbstractLoggedInVoter
{
    public const SHARED = 'profile.shared';

    #[Override]
    protected function resolveContext(TokenInterface $token): ?array
    {
        $context = parent::resolveContext($token);
        if (null === $context) {
            return null;
        }

        $user = $context[User::class] ?? null;
        if (!$user instanceof User || !$user->profile instanceof Profile) {
            return null;
        }

        return [Profile::class => $user->profile] + $context;
    }

    #[Grants(self::SHARED)]
    protected function canShare(Profile $profile): bool
    {
        return 'shared' === $profile->name;
    }
}
