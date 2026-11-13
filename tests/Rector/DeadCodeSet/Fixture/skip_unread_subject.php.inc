<?php

namespace Silarhi\VoterBundle\Tests\Rector\DeadCodeSet\Fixture;

use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Post;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

final class SkipUnreadSubject extends AbstractRuleVoter
{
    #[Grants('post.export')]
    private function canExport(Post $subject): bool
    {
        return true;
    }
}

?>
