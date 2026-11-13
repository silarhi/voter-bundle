<?php

namespace Silarhi\VoterBundle\Tests\Rector\DeadCodeSet\Fixture;

use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

final class KeepGrantsMethods extends AbstractRuleVoter
{
    #[Grants('post.show')]
    private function canShow(): bool
    {
        return $this->isOpen();
    }

    #[\Silarhi\VoterBundle\Attribute\Grants('post.edit')]
    private function canEdit(): bool
    {
        return true;
    }

    private function isOpen(): bool
    {
        return true;
    }

    private function forgotten(): bool
    {
        return true;
    }
}

?>
-----
<?php

namespace Silarhi\VoterBundle\Tests\Rector\DeadCodeSet\Fixture;

use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

final class KeepGrantsMethods extends AbstractRuleVoter
{
    #[Grants('post.show')]
    private function canShow(): bool
    {
        return $this->isOpen();
    }

    #[\Silarhi\VoterBundle\Attribute\Grants('post.edit')]
    private function canEdit(): bool
    {
        return true;
    }

    private function isOpen(): bool
    {
        return true;
    }
}

?>
