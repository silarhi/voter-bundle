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

namespace Silarhi\VoterBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Silarhi\VoterBundle\DependencyInjection\RegisterRulesPass;
use Silarhi\VoterBundle\Exception\InvalidRuleException;
use Silarhi\VoterBundle\Rule\RuleLoader;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\AbstractLoggedInVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\AbstractProfileVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\EmptyVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\PostVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\ProfileVoter;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;

final class RegisterRulesPassTest extends TestCase
{
    public function testCompilesTheRulesOfTheVoters(): void
    {
        $container = new ContainerBuilder();
        $container->register('post_voter', PostVoter::class)->addTag(RegisterRulesPass::VOTER_TAG);
        $container->setParameter('profile_voter.class', ProfileVoter::class);
        $container->register('profile_voter', '%profile_voter.class%')->addTag(RegisterRulesPass::VOTER_TAG);
        $container->register(PostVoter::class)->addTag(RegisterRulesPass::VOTER_TAG);

        (new RegisterRulesPass())->process($container);

        self::assertSame([['setRules', [RuleLoader::load(new ReflectionClass(PostVoter::class))]]], $container->getDefinition('post_voter')->getMethodCalls());
        self::assertSame([['setRules', [RuleLoader::load(new ReflectionClass(ProfileVoter::class))]]], $container->getDefinition('profile_voter')->getMethodCalls());
        self::assertCount(1, $container->getDefinition(PostVoter::class)->getMethodCalls(), 'the id is the class');
    }

    public function testTracksTheFilesOfTheVoterAndItsParents(): void
    {
        $container = new ContainerBuilder();
        $container->register('profile_voter', ProfileVoter::class)->addTag(RegisterRulesPass::VOTER_TAG);

        (new RegisterRulesPass())->process($container);

        $files = array_map(
            static fn (FileResource $resource): string => $resource->getResource(),
            array_values(array_filter($container->getResources(), static fn (object $resource): bool => $resource instanceof FileResource)),
        );

        foreach ([ProfileVoter::class, AbstractProfileVoter::class, AbstractLoggedInVoter::class] as $class) {
            self::assertContains((new ReflectionClass($class))->getFileName(), $files);
        }

        self::assertNotContains((new ReflectionClass(AbstractRuleVoter::class))->getFileName(), $files);
    }

    public function testLeavesTheOtherVotersAlone(): void
    {
        $container = new ContainerBuilder();
        $container->register('role_voter', RoleVoter::class)->addTag(RegisterRulesPass::VOTER_TAG);
        $container->register('missing_voter', 'App\Missing')->addTag(RegisterRulesPass::VOTER_TAG);
        $container->register('abstract_voter', PostVoter::class)->addTag(RegisterRulesPass::VOTER_TAG)->setAbstract(true);
        $container->register('untagged_voter', PostVoter::class);
        $container->setParameter('array_voter.class', [PostVoter::class]);
        $container->register('array_voter', '%array_voter.class%')->addTag(RegisterRulesPass::VOTER_TAG);

        (new RegisterRulesPass())->process($container);

        foreach (['role_voter', 'missing_voter', 'abstract_voter', 'untagged_voter', 'array_voter'] as $id) {
            self::assertSame([], $container->getDefinition($id)->getMethodCalls(), $id);
        }
    }

    public function testFailsTheBuildOnAnInvalidVoter(): void
    {
        $container = new ContainerBuilder();
        $container->register('empty_voter', EmptyVoter::class)->addTag(RegisterRulesPass::VOTER_TAG);

        $this->expectException(InvalidRuleException::class);
        $this->expectExceptionMessage('grants nothing');

        (new RegisterRulesPass())->process($container);
    }
}
