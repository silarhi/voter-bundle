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

namespace Silarhi\VoterBundle\Rector\Set;

/**
 * Rector sets of the bundle, for `->withSets()`.
 */
final class VoterSetList
{
    /**
     * Keeps the #[Grants] methods and their parameters out of the dead-code set: add it next to
     * `->withPreparedSets(deadCode: true)`.
     */
    public const DEAD_CODE = __DIR__ . '/../../../config/rector/dead-code.php';
}
