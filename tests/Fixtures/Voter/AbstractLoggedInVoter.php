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
use Silarhi\VoterBundle\Attribute\ProvidesArgument;
use Silarhi\VoterBundle\Tests\Fixtures\Model\User;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The first context layer: denies without a logged-in user, hands the methods the User otherwise.
 */
#[ProvidesArgument(User::class)]
abstract class AbstractLoggedInVoter extends AbstractRuleVoter
{
    #[Override]
    protected function resolveContext(TokenInterface $token): ?array
    {
        $user = $token->getUser();
        $context = parent::resolveContext($token);
        if (!$user instanceof User || null === $context) {
            return null;
        }

        return [User::class => $user] + $context;
    }
}
