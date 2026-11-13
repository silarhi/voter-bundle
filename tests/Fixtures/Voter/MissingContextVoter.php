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

use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Attribute\ProvidesArgument;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Profile;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

/**
 * Declares a Profile that resolveContext() never returns.
 */
#[ProvidesArgument(Profile::class)]
final class MissingContextVoter extends AbstractRuleVoter
{
    public const ATTRIBUTE = 'missing';

    #[Grants(self::ATTRIBUTE)]
    private function decide(Profile $profile): bool
    {
        return '' !== $profile->name;
    }
}
