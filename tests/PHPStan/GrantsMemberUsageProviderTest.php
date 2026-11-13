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
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ShipMonk\PHPStan\DeadCode\Rule\DeadCodeRule;

/**
 * @extends RuleTestCase<DeadCodeRule>
 */
final class GrantsMemberUsageProviderTest extends RuleTestCase
{
    public function testKeepsTheGrantsMethodsOfTheVoters(): void
    {
        $this->analyse([
            __DIR__ . '/Data/AbstractShipmonkVoter.php',
            __DIR__ . '/Data/ShipmonkVoter.php',
            __DIR__ . '/Data/NotAShipmonkVoter.php',
        ], [
            ['Unused Silarhi\VoterBundle\Tests\PHPStan\Data\NotAShipmonkVoter::decide', 21],
            ['Unused Silarhi\VoterBundle\Tests\PHPStan\Data\ShipmonkVoter::forgotten', 26],
        ]);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../vendor/shipmonk/dead-code-detector/rules.neon',
            __DIR__ . '/../../shipmonk.neon',
        ];
    }

    #[Override]
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(DeadCodeRule::class);
    }

    /**
     * @return list<Collector<\PhpParser\Node, mixed>>
     */
    #[Override]
    protected function getCollectors(): array
    {
        /** @var list<Collector<\PhpParser\Node, mixed>> $collectors */
        $collectors = self::getContainer()->getServicesByTag('phpstan.collector');

        return array_values(array_filter($collectors, static fn (Collector $collector): bool => str_starts_with($collector::class, 'ShipMonk\\')));
    }
}
