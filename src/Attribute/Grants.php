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

use function sprintf;

/**
 * Grants security attributes: on a method of an AbstractRuleVoter, the method decides them; on the voter class, they
 * are granted as soon as the context of the voter resolves (its guards pass).
 *
 * A method takes the subject in a parameter named $subject, whose type tells which subjects it votes on; without one,
 * it votes on a null subject only. A class-level grant has no method: it lists its subjects.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Grants
{
    /**
     * @var non-empty-list<non-empty-string>
     */
    public array $attributes;

    /**
     * @var non-empty-list<class-string|null>|null
     */
    public ?array $subjects;

    /**
     * @param string|list<string>          $attribute the security attribute(s), as passed to isGranted()
     * @param list<class-string|null>|null $subjects  on a class only: the classes it votes on, null for no subject
     */
    public function __construct(string|array $attribute, ?array $subjects = null)
    {
        $attributes = is_string($attribute) ? [$attribute] : $attribute;
        if ([] === $attributes) {
            throw new InvalidRuleException('#[Grants] needs at least one security attribute.');
        }

        $valid = [];
        foreach ($attributes as $name) {
            if ('' === $name) {
                throw new InvalidRuleException('#[Grants] cannot grant an empty security attribute.');
            }

            $valid[] = $name;
        }

        if ([] === $subjects) {
            throw new InvalidRuleException(sprintf('#[Grants("%s")] lists no subjects: list null to vote without a subject.', $valid[0]));
        }

        $this->attributes = $valid;
        $this->subjects = $subjects;
    }
}
