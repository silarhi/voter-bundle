<?php

namespace Silarhi\VoterBundle\Tests\Rector\DeadCodeSet\Fixture;

use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Post;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

final class KeepGrantsParameters extends AbstractRuleVoter
{
    #[Grants('post.export')]
    private function canExport(Post $subject): bool
    {
        return $this->check('export', 1);
    }

    private function check(string $what, int $unused): bool
    {
        return '' !== $what;
    }
}

?>
-----
<?php

namespace Silarhi\VoterBundle\Tests\Rector\DeadCodeSet\Fixture;

use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Post;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

final class KeepGrantsParameters extends AbstractRuleVoter
{
    #[Grants('post.export')]
    private function canExport(Post $subject): bool
    {
        return $this->check('export');
    }

    private function check(string $what): bool
    {
        return '' !== $what;
    }
}

?>
