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

namespace Silarhi\VoterBundle\Tests\Voter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Silarhi\VoterBundle\Exception\MissingArgumentException;
use Silarhi\VoterBundle\Rule\Rule;
use Silarhi\VoterBundle\Rule\RuleLoader;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Comment;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Post;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Profile;
use Silarhi\VoterBundle\Tests\Fixtures\Model\SpecialPost;
use Silarhi\VoterBundle\Tests\Fixtures\Model\User;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\MissingContextVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\PostVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\ProfileVoter;

use function sprintf;

use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AbstractRuleVoterTest extends TestCase
{
    /**
     * @param VoterInterface::ACCESS_* $expected
     */
    #[DataProvider('providePostVotes')]
    public function testVotesWithTheRuleOfTheAttribute(string $attribute, mixed $subject, int $expected): void
    {
        self::assertSame($expected, (new PostVoter())->vote(self::token('alice'), $subject, [$attribute]));
    }

    /**
     * @return iterable<string, array{string, mixed, VoterInterface::ACCESS_*}>
     */
    public static function providePostVotes(): iterable
    {
        yield 'no subject' => [PostVoter::LIST, null, VoterInterface::ACCESS_GRANTED];
        yield 'a subject where none is expected' => [PostVoter::LIST, new Post(), VoterInterface::ACCESS_ABSTAIN];
        yield 'the subject class' => [PostVoter::SHOW, new Post(), VoterInterface::ACCESS_GRANTED];
        yield 'the method denies' => [PostVoter::SHOW, new Post(published: false), VoterInterface::ACCESS_DENIED];
        yield 'a subclass' => [PostVoter::SHOW, new SpecialPost(), VoterInterface::ACCESS_GRANTED];
        yield 'no subject where one is expected' => [PostVoter::SHOW, null, VoterInterface::ACCESS_ABSTAIN];
        yield 'another subject class' => [PostVoter::SHOW, new Comment(), VoterInterface::ACCESS_ABSTAIN];
        yield 'the token' => [PostVoter::EDIT, new Post('alice'), VoterInterface::ACCESS_GRANTED];
        yield 'the token denies' => [PostVoter::EDIT, new Post('bob'), VoterInterface::ACCESS_DENIED];
        yield 'a second attribute of the method' => [PostVoter::DELETE, new Post('bob'), VoterInterface::ACCESS_DENIED];
        yield 'a union member' => [PostVoter::REPORT, new Comment(), VoterInterface::ACCESS_GRANTED];
        yield 'class-level with a subject' => [PostVoter::EXPORT, new Post(), VoterInterface::ACCESS_GRANTED];
        yield 'class-level without a subject' => [PostVoter::EXPORT, null, VoterInterface::ACCESS_GRANTED];
        yield 'class-level, another subject' => [PostVoter::EXPORT, new Comment(), VoterInterface::ACCESS_ABSTAIN];
        yield 'an unknown attribute' => [ProfileVoter::ACCESS, null, VoterInterface::ACCESS_ABSTAIN];
    }

    public function testPassesTheAttributeAndTheVote(): void
    {
        if (!class_exists(Vote::class)) {
            self::markTestSkipped('Symfony 7.3 added the Vote object.');
        }

        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_GRANTED, (new PostVoter())->vote(self::token('alice'), new Comment(), [PostVoter::REPORT], $vote));
        self::assertSame([sprintf('"post.report" is open to every %s.', Comment::class)], $vote->reasons);
    }

    public function testTellsTheAccessDecisionManagerWhatItSupports(): void
    {
        $voter = new PostVoter();

        self::assertTrue($voter->supportsAttribute(PostVoter::SHOW));
        self::assertFalse($voter->supportsAttribute(ProfileVoter::ACCESS));
        self::assertTrue($voter->supportsType(SpecialPost::class));
        self::assertTrue($voter->supportsType('null'));
        self::assertFalse($voter->supportsType(Profile::class));
    }

    /**
     * @param VoterInterface::ACCESS_* $expected
     */
    #[DataProvider('provideContextVotes')]
    public function testResolvesTheContextBeforeTheRules(TokenInterface $token, string $attribute, mixed $subject, int $expected): void
    {
        self::assertSame($expected, (new ProfileVoter())->vote($token, $subject, [$attribute]));
    }

    /**
     * @return iterable<string, array{TokenInterface, string, mixed, VoterInterface::ACCESS_*}>
     */
    public static function provideContextVotes(): iterable
    {
        yield 'no user denies' => [new NullToken(), ProfileVoter::ACCESS, null, VoterInterface::ACCESS_DENIED];
        yield 'no profile denies' => [self::token('alice'), ProfileVoter::ACCESS, null, VoterInterface::ACCESS_DENIED];
        yield 'class-level once resolved' => [self::token('alice', 'reader'), ProfileVoter::ACCESS, null, VoterInterface::ACCESS_GRANTED];
        yield 'the arguments of both layers' => [self::token('alice', 'editor'), ProfileVoter::EDIT, new Post('alice'), VoterInterface::ACCESS_GRANTED];
        yield 'the arguments deny' => [self::token('alice', 'reader'), ProfileVoter::EDIT, new Post('alice'), VoterInterface::ACCESS_DENIED];
        yield 'a rule of the parent' => [self::token('alice', 'shared'), ProfileVoter::SHARED, null, VoterInterface::ACCESS_GRANTED];
        yield 'a rule of the parent denies' => [self::token('alice', 'reader'), ProfileVoter::SHARED, null, VoterInterface::ACCESS_DENIED];
        yield 'no context, no rule' => [new NullToken(), ProfileVoter::SHARED, null, VoterInterface::ACCESS_DENIED];
    }

    public function testFailsWhenTheContextMissesADeclaredArgument(): void
    {
        $this->expectException(MissingArgumentException::class);
        $this->expectExceptionMessage(sprintf('asks for %s, but %s::resolveContext() returned none', Profile::class, MissingContextVoter::class));

        (new MissingContextVoter())->vote(self::token('alice'), null, [MissingContextVoter::ATTRIBUTE]);
    }

    public function testUsesTheRulesOfTheContainerOverReflection(): void
    {
        $voter = new PostVoter();
        $voter->setRules(['custom' => (new Rule(PostVoter::class, 'canList', ['null'], []))->toArray()]);

        self::assertTrue($voter->supportsAttribute('custom'));
        self::assertFalse($voter->supportsAttribute(PostVoter::LIST));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(self::token('alice'), null, ['custom']));
    }

    public function testVotesTheSameWithTheCompiledRules(): void
    {
        $compiled = new PostVoter();
        $compiled->setRules(RuleLoader::load(new ReflectionClass(PostVoter::class)));

        $token = self::token('alice');
        foreach ([[PostVoter::SHOW, new Post(published: false)], [PostVoter::EDIT, new Post('alice')], [PostVoter::LIST, new Post()]] as [$attribute, $subject]) {
            self::assertSame((new PostVoter())->vote($token, $subject, [$attribute]), $compiled->vote($token, $subject, [$attribute]));
        }
    }

    private static function token(string $identifier, ?string $profile = null): TokenInterface
    {
        $user = new User($identifier, null === $profile ? null : new Profile($profile));

        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
