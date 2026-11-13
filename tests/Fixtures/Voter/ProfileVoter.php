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
use Silarhi\VoterBundle\Tests\Fixtures\Model\Post;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Profile;
use Silarhi\VoterBundle\Tests\Fixtures\Model\User;

#[Grants(self::ACCESS, subjects: [null])]
final class ProfileVoter extends AbstractProfileVoter
{
    public const ACCESS = 'profile.access';

    public const EDIT = 'profile.post.edit';

    #[Grants(self::EDIT)]
    private function canEdit(Profile $profile, Post $subject, User $user): bool
    {
        return 'editor' === $profile->name && $subject->author === $user->getUserIdentifier();
    }
}
