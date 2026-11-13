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

namespace Silarhi\VoterBundle\Attribute;

use Attribute;

use function is_string;

use Silarhi\VoterBundle\Exception\InvalidRuleException;

/**
 * Declares the arguments a voter, or an abstract voter its children extend, hands to the #[Grants] methods: the
 * values resolveContext() returns, keyed by these classes. A method asks for one by type.
 *
 * The declaration lets the container check every method when it builds; resolveContext() supplies the values.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class ProvidesArgument
{
    /**
     * @var non-empty-list<class-string>
     */
    public array $classes;

    /**
     * @param class-string|list<class-string> $class
     */
    public function __construct(string|array $class)
    {
        $classes = is_string($class) ? [$class] : $class;
        if ([] === $classes) {
            throw new InvalidRuleException('#[ProvidesArgument] needs at least one class.');
        }

        $this->classes = $classes;
    }
}
