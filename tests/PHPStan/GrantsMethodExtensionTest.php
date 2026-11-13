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

namespace Silarhi\VoterBundle\Tests\PHPStan;

use Override;
use PHPStan\Rules\DeadCode\UnusedPrivateMethodRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<UnusedPrivateMethodRule>
 */
final class GrantsMethodExtensionTest extends RuleTestCase
{
    public function testKeepsTheGrantsMethodsOfTheVoters(): void
    {
        $this->analyse([__DIR__ . '/Data/SomeVoter.php', __DIR__ . '/Data/NotAVoter.php'], [
            ['Method Silarhi\VoterBundle\Tests\PHPStan\Data\SomeVoter::forgotten() is unused.', 27],
            ['Method Silarhi\VoterBundle\Tests\PHPStan\Data\NotAVoter::decide() is unused.', 20],
        ]);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }

    #[Override]
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(UnusedPrivateMethodRule::class);
    }
}
