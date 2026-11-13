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
use Silarhi\VoterBundle\Tests\Fixtures\Model\Comment;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Post;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

use function sprintf;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Every kind of rule, without context: no subject, a class, two attributes on one method, a union with the token,
 * the attribute and the vote, a class-level grant.
 */
#[Grants(self::EXPORT, subjects: [Post::class, null])]
final class PostVoter extends AbstractRuleVoter
{
    public const LIST = 'post.list';

    public const SHOW = 'post.show';

    public const EDIT = 'post.edit';

    public const DELETE = 'post.delete';

    public const REPORT = 'post.report';

    public const EXPORT = 'post.export';

    #[Grants(self::LIST)]
    private function canList(): bool
    {
        return true;
    }

    #[Grants(self::SHOW)]
    private function canShow(Post $subject): bool
    {
        return $subject->published;
    }

    #[Grants(self::EDIT)]
    #[Grants(self::DELETE)]
    private function canEdit(Post $subject, TokenInterface $token): bool
    {
        return $subject->author === $token->getUserIdentifier();
    }

    #[Grants(self::REPORT)]
    private function canReport(string $attribute, Post|Comment $subject, ?Vote $vote): bool
    {
        $vote?->addReason(sprintf('"%s" is open to every %s.', $attribute, $subject::class));

        return true;
    }
}
