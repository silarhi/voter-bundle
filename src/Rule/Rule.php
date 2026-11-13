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

namespace Silarhi\VoterBundle\Rule;

use function in_array;

/**
 * How a voter decides one security attribute: the method that decides it (none for a class-level grant), the
 * subjects it votes on and the arguments the method takes, in order.
 *
 * Plain data, so the container can dump it: an argument is a marker ($subject, $attribute, $token, $vote) or the
 * class of a value resolveContext() returns.
 *
 * @phpstan-type RuleShape array{class: class-string, method: string|null, subjects: list<string>, arguments: list<string>}
 *
 * @internal
 */
final readonly class Rule
{
    public const SUBJECT = '$subject';

    public const ATTRIBUTE = '$attribute';

    public const TOKEN = '$token';

    public const VOTE = '$vote';

    /** A subject of any type. */
    public const ANY = 'mixed';

    /** Any object. */
    public const OBJECT = 'object';

    /** The types get_debug_type() names without a class. */
    public const BUILTIN_TYPES = ['null', 'bool', 'int', 'float', 'string', 'array'];

    /**
     * @param class-string $class     the class declaring the method, whose scope calls it
     * @param list<string> $subjects  class names, builtin type names, "object" or "mixed"
     * @param list<string> $arguments markers or classes, in the order of the parameters
     */
    public function __construct(
        public string $class,
        public ?string $method,
        public array $subjects,
        public array $arguments,
    ) {
    }

    /**
     * @param RuleShape $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['class'], $data['method'], $data['subjects'], $data['arguments']);
    }

    /**
     * @return RuleShape
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'method' => $this->method,
            'subjects' => $this->subjects,
            'arguments' => $this->arguments,
        ];
    }

    /**
     * @param string $type the type of a subject, as get_debug_type() names it
     */
    public function supportsType(string $type): bool
    {
        $isObject = !in_array($type, self::BUILTIN_TYPES, true) && !str_starts_with($type, 'resource');

        foreach ($this->subjects as $subject) {
            if (self::ANY === $subject || $subject === $type) {
                return true;
            }

            if (!$isObject || in_array($subject, self::BUILTIN_TYPES, true)) {
                continue;
            }

            // subclasses too, Doctrine proxies among them
            if (self::OBJECT === $subject || is_a($type, $subject, true)) {
                return true;
            }
        }

        return false;
    }
}
