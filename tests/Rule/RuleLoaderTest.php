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

use Closure;

use function count;

use Countable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Attribute\ProvidesArgument;
use Silarhi\VoterBundle\Exception\InvalidRuleException;
use Silarhi\VoterBundle\Rule\Rule;
use Silarhi\VoterBundle\Rule\RuleLoader;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Comment;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Post;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Profile;
use Silarhi\VoterBundle\Tests\Fixtures\Model\User;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\AbstractProfileVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\PostVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\ProfileVoter;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;
use stdClass;
use Stringable;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class RuleLoaderTest extends TestCase
{
    public function testLoadsEveryKindOfRule(): void
    {
        self::assertSame([
            PostVoter::EXPORT => ['class' => PostVoter::class, 'method' => null, 'subjects' => [Post::class, 'null'], 'arguments' => []],
            PostVoter::LIST => ['class' => PostVoter::class, 'method' => 'canList', 'subjects' => ['null'], 'arguments' => []],
            PostVoter::SHOW => ['class' => PostVoter::class, 'method' => 'canShow', 'subjects' => [Post::class], 'arguments' => [Rule::SUBJECT]],
            PostVoter::EDIT => ['class' => PostVoter::class, 'method' => 'canEdit', 'subjects' => [Post::class], 'arguments' => [Rule::SUBJECT, Rule::TOKEN]],
            PostVoter::DELETE => ['class' => PostVoter::class, 'method' => 'canEdit', 'subjects' => [Post::class], 'arguments' => [Rule::SUBJECT, Rule::TOKEN]],
            PostVoter::REPORT => ['class' => PostVoter::class, 'method' => 'canReport', 'subjects' => [Post::class, Comment::class], 'arguments' => [Rule::ATTRIBUTE, Rule::SUBJECT, Rule::VOTE]],
        ], RuleLoader::load(new ReflectionClass(PostVoter::class)));
    }

    public function testLoadsTheProvidedArgumentsAndTheInheritedRules(): void
    {
        self::assertSame([
            ProfileVoter::ACCESS => ['class' => ProfileVoter::class, 'method' => null, 'subjects' => ['null'], 'arguments' => []],
            ProfileVoter::EDIT => ['class' => ProfileVoter::class, 'method' => 'canEdit', 'subjects' => [Post::class], 'arguments' => [Profile::class, Rule::SUBJECT, User::class]],
            ProfileVoter::SHARED => ['class' => AbstractProfileVoter::class, 'method' => 'canShare', 'subjects' => ['null'], 'arguments' => [Profile::class]],
        ], RuleLoader::load(new ReflectionClass(ProfileVoter::class)));
    }

    public function testAProvidedArgumentAnswersAParentType(): void
    {
        $voter = new #[ProvidesArgument(User::class)] class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(UserInterface $user): bool
            {
                return true;
            }
        };

        self::assertSame([User::class], RuleLoader::load(new ReflectionClass($voter))['a']['arguments']);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideSubjects')]
    public function testReadsTheSubjectsFromTheType(Closure $voter, array $expected): void
    {
        $object = $voter();
        self::assertIsObject($object);

        self::assertSame($expected, RuleLoader::load(new ReflectionClass($object))['a']['subjects']);
    }

    /**
     * @return iterable<string, array{Closure(): object, list<string>}>
     */
    public static function provideSubjects(): iterable
    {
        yield 'no subject' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(): bool
            {
                return true;
            }
        }, ['null']];
        yield 'untyped' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide($subject): bool
            {
                return true;
            }
        }, [Rule::ANY]];
        yield 'mixed' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(mixed $subject): bool
            {
                return true;
            }
        }, [Rule::ANY]];
        yield 'nullable' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(?Post $subject): bool
            {
                return true;
            }
        }, [Post::class, 'null']];
        yield 'union with null' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(Post|Comment|null $subject): bool
            {
                return true;
            }
        }, [Post::class, Comment::class, 'null']];
        yield 'scalar and object' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(string|object $subject): bool
            {
                return '' !== $subject;
            }
        }, ['object', 'string']];
    }

    public function testRefusesAnAbstractVoter(): void
    {
        $this->expectException(InvalidRuleException::class);
        $this->expectExceptionMessage('is not a concrete');

        RuleLoader::load(new ReflectionClass(AbstractProfileVoter::class));
    }

    #[DataProvider('provideInvalidVoters')]
    public function testRefusesWhatTheVoterCannotHonor(Closure $voter, string $message): void
    {
        $this->expectException(InvalidRuleException::class);
        $this->expectExceptionMessage($message);

        $object = $voter();
        self::assertIsObject($object);
        RuleLoader::load(new ReflectionClass($object));
    }

    /**
     * @return iterable<string, array{Closure(): object, string}>
     */
    public static function provideInvalidVoters(): iterable
    {
        yield 'not a rule voter' => [static fn (): object => new stdClass(), 'is not a concrete'];
        yield 'nothing granted' => [static fn (): object => new class extends AbstractRuleVoter {
        }, 'grants nothing'];
        yield 'granted twice' => [static fn (): object => new #[Grants('a', subjects: [null])] class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(): bool
            {
                return true;
            }
        }, '"a" is granted twice'];
        yield 'no attribute' => [static fn (): object => new #[Grants([])] class extends AbstractRuleVoter {
        }, 'needs at least one security attribute'];
        yield 'an empty attribute' => [static fn (): object => new #[Grants('')] class extends AbstractRuleVoter {
        }, 'empty security attribute'];
        yield 'no subjects listed' => [static fn (): object => new #[Grants('a', subjects: [])] class extends AbstractRuleVoter {
        }, 'lists no subjects'];
        yield 'class-level without subjects' => [static fn (): object => new #[Grants('a')] class extends AbstractRuleVoter {
        }, 'must list its subjects'];
        yield 'class-level unknown subject' => [static fn (): object => new #[Grants('a', subjects: ['App\Missing'])] class extends AbstractRuleVoter {
        }, 'lists the subject "App\Missing"'];
        yield 'method-level subjects' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a', subjects: [Post::class])]
            private function decide(Post $subject): bool
            {
                return $subject->published;
            }
        }, 'cannot list subjects: type its $subject parameter'];
        yield 'static method' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private static function decide(): bool
            {
                return true;
            }
        }, 'cannot be static'];
        yield 'no return type' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide()
            {
                return true;
            }
        }, 'must declare a bool return type'];
        yield 'nullable bool' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(): ?bool
            {
                return null;
            }
        }, 'must declare a bool return type'];
        yield 'variadic' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(Post ...$subject): bool
            {
                return [] !== $subject;
            }
        }, 'variadic parameter $subject'];
        yield 'attribute not a string' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(int $attribute): bool
            {
                return 0 !== $attribute;
            }
        }, 'The $attribute parameter'];
        yield 'scalar argument' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(int $count): bool
            {
                return 0 !== $count;
            }
        }, 'The parameter $count'];
        yield 'token implementation' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(UsernamePasswordToken $token): bool
            {
                return '' !== $token->getFirewallName();
            }
        }, 'the token can be any implementation'];
        yield 'token twice' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(TokenInterface $token, TokenInterface $again): bool
            {
                return $token === $again;
            }
        }, 'asks twice for $token'];
        yield 'vote not nullable' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(Vote $vote): bool
            {
                return [] === $vote->reasons;
            }
        }, 'must be a nullable'];
        yield 'not provided' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(User $user): bool
            {
                return true;
            }
        }, 'which the voter does not provide'];
        yield 'a builtin argument' => [static fn (): object => new #[ProvidesArgument([User::class, Profile::class])] class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(object $any): bool
            {
                return true;
            }
        }, 'The parameter $any'];
        yield 'ambiguous' => [static fn (): object => new #[ProvidesArgument([User::class, InMemoryUser::class])] class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(UserInterface $user): bool
            {
                return true;
            }
        }, 'matches several provided classes'];
        yield 'subject unknown class' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(\App\Missing $subject): bool
            {
                return true;
            }
        }, 'typed with "App\Missing"'];
        yield 'subject intersection' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(Countable&Stringable $subject): bool
            {
                return 0 !== count($subject);
            }
        }, 'cannot be an intersection type'];
        yield 'subject with an intersection' => [static fn (): object => new class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide((Countable&Stringable)|null $subject): bool
            {
                return true;
            }
        }, 'cannot have an intersection in its type'];
        yield 'subject iterable' => [static fn (): object => new class extends AbstractRuleVoter {
            /** @param iterable<mixed> $subject */
            #[Grants('a')]
            private function decide(iterable $subject): bool
            {
                return true;
            }
        }, 'cannot be typed "iterable"'];
        yield 'provides an unknown class' => [static fn (): object => new #[ProvidesArgument('App\Missing')] class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(): bool
            {
                return true;
            }
        }, 'names "App\Missing"'];
        yield 'provides the token' => [static fn (): object => new #[ProvidesArgument(TokenInterface::class)] class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(): bool
            {
                return true;
            }
        }, 'the methods already get it by type'];
        yield 'provides nothing' => [static fn (): object => new #[ProvidesArgument([])] class extends AbstractRuleVoter {
            #[Grants('a')]
            private function decide(): bool
            {
                return true;
            }
        }, 'needs at least one class'];
    }
}
