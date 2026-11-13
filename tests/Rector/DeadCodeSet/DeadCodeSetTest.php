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

namespace Silarhi\VoterBundle\Tests\Rector\DeadCodeSet;

use Iterator;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class DeadCodeSetTest extends AbstractRectorTestCase
{
    #[DataProvider('provideFixtures')]
    public function testKeepsTheGrantsMethodsAndTheirParameters(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /**
     * @return Iterator<array-key, array<array-key, string>>
     */
    public static function provideFixtures(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    #[Override]
    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule.php';
    }
}
