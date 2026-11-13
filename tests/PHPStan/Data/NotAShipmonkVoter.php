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

namespace Silarhi\VoterBundle\Tests\PHPStan\Data;

use Silarhi\VoterBundle\Attribute\Grants;

final class NotAShipmonkVoter
{
    #[Grants('some.attribute')]
    public function decide(): bool
    {
        return true;
    }
}
