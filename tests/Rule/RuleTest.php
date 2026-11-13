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

namespace Silarhi\VoterBundle\Tests\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silarhi\VoterBundle\Rule\Rule;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Comment;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Post;
use Silarhi\VoterBundle\Tests\Fixtures\Model\SpecialPost;
use Stringable;

final class RuleTest extends TestCase
{
    /**
     * @param list<string> $subjects
     */
    #[DataProvider('provideSupportsType')]
    public function testSupportsType(array $subjects, string $type, bool $expected): void
    {
        $rule = new Rule(self::class, 'decide', $subjects, []);

        self::assertSame($expected, $rule->supportsType($type));
    }

    /**
     * @return iterable<string, array{list<string>, string, bool}>
     */
    public static function provideSupportsType(): iterable
    {
        yield 'the class' => [[Post::class], Post::class, true];
        yield 'a subclass' => [[Post::class], SpecialPost::class, true];
        yield 'another class' => [[Post::class], Comment::class, false];
        yield 'null without null' => [[Post::class], 'null', false];
        yield 'null' => [[Post::class, 'null'], 'null', true];
        yield 'an interface' => [[Stringable::class], Post::class, false];
        yield 'a union member' => [[Post::class, Comment::class], Comment::class, true];
        yield 'mixed takes null' => [[Rule::ANY], 'null', true];
        yield 'mixed takes a class' => [[Rule::ANY], Comment::class, true];
        yield 'object takes a class' => [[Rule::OBJECT], Comment::class, true];
        yield 'object refuses null' => [[Rule::OBJECT], 'null', false];
        yield 'object refuses a scalar' => [[Rule::OBJECT], 'string', false];
        yield 'object refuses a resource' => [[Rule::OBJECT], 'resource (stream)', false];
        yield 'a scalar' => [['string'], 'string', true];
        yield 'no int for a float' => [['float'], 'int', false];
        yield 'a scalar is no class' => [[Post::class], 'string', false];
        yield 'a class is no scalar' => [['string'], Post::class, false];
    }

    public function testRoundTripsThroughAnArray(): void
    {
        $rule = new Rule(self::class, null, [Post::class, 'null'], [Rule::SUBJECT, Rule::TOKEN]);

        self::assertEquals($rule, Rule::fromArray($rule->toArray()));
    }
}
