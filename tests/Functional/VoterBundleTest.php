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

namespace Silarhi\VoterBundle\Tests\Functional;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Comment;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Post;
use Silarhi\VoterBundle\Tests\Fixtures\Model\Profile;
use Silarhi\VoterBundle\Tests\Fixtures\Model\User;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\PostVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\ProfileVoter;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

use function sprintf;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;

/**
 * The voters of an application, autoconfigured, through the access decision manager.
 */
final class VoterBundleTest extends KernelTestCase
{
    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
    }

    /**
     * @param list<string> $attributes
     */
    #[DataProvider('provideDecisions')]
    public function testDecidesThroughTheRules(TokenInterface $token, array $attributes, mixed $subject, bool $expected): void
    {
        $decisionManager = self::getContainer()->get('security.access.decision_manager');
        self::assertInstanceOf(AccessDecisionManagerInterface::class, $decisionManager);

        self::assertSame($expected, $decisionManager->decide($token, $attributes, $subject));
    }

    /**
     * @return iterable<string, array{TokenInterface, list<string>, mixed, bool}>
     */
    public static function provideDecisions(): iterable
    {
        $alice = new UsernamePasswordToken(new User('alice', new Profile('editor')), 'main', ['ROLE_USER']);

        yield 'granted' => [$alice, [PostVoter::SHOW], new Post(), true];
        yield 'denied' => [$alice, [PostVoter::SHOW], new Post(published: false), false];
        yield 'no voter votes on that subject' => [$alice, [PostVoter::SHOW], new Comment(), false];
        yield 'with the context' => [$alice, [ProfileVoter::EDIT], new Post('alice'), true];
        yield 'without the context' => [new NullToken(), [ProfileVoter::ACCESS], null, false];
    }

    public function testTheVotersGetTheCompiledRules(): void
    {
        foreach ([PostVoter::class, ProfileVoter::class] as $id) {
            $voter = self::getContainer()->get($id);
            if ($voter instanceof TraceableVoter) {
                $voter = $voter->getDecoratedVoter();
            }

            self::assertInstanceOf(AbstractRuleVoter::class, $voter);
            self::assertNotNull((new ReflectionProperty(AbstractRuleVoter::class, 'rules'))->getValue($voter), sprintf('%s read its rules by reflection', $id));
        }
    }
}
