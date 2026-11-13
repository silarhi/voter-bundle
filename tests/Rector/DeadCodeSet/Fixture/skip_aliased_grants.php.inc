<?php

namespace Silarhi\VoterBundle\Tests\Rector\DeadCodeSet\Fixture;

use Silarhi\VoterBundle\Attribute\Grants as Allows;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

final class SkipAliasedGrants extends AbstractRuleVoter
{
    #[Allows('post.show')]
    private function canShow(): bool
    {
        return true;
    }
}

?>
