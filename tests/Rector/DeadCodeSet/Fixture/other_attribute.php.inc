<?php

namespace Silarhi\VoterBundle\Tests\Rector\DeadCodeSet\Fixture;

use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

final class OtherAttribute extends AbstractRuleVoter
{
    #[\Deprecated]
    private function forgotten(): bool
    {
        return true;
    }
}

?>
-----
<?php

namespace Silarhi\VoterBundle\Tests\Rector\DeadCodeSet\Fixture;

use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

final class OtherAttribute extends AbstractRuleVoter
{
}

?>
