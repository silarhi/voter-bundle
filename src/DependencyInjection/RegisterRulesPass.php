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

namespace Silarhi\VoterBundle\DependencyInjection;

use function is_string;

use Override;
use ReflectionClass;
use Silarhi\VoterBundle\Rule\RuleLoader;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiles the rules of every AbstractRuleVoter into the container: the voters read no attribute at runtime, and a
 * #[Grants] the bundle cannot honor fails the build.
 */
final class RegisterRulesPass implements CompilerPassInterface
{
    public const VOTER_TAG = 'security.voter';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(self::VOTER_TAG)) as $id) {
            $definition = $container->findDefinition($id);
            if ($definition->isAbstract()) {
                continue;
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);
            if (!is_string($class)) {
                continue;
            }

            $reflection = $container->getReflectionClass($class, false);
            if (!$reflection instanceof ReflectionClass || !$reflection->isSubclassOf(AbstractRuleVoter::class)) {
                continue;
            }

            // The container tracks the public and protected methods of a class only: rules sit on private ones too.
            for ($current = $reflection; false !== $current && AbstractRuleVoter::class !== $current->getName(); $current = $current->getParentClass()) {
                $file = $current->getFileName();
                if (false !== $file) {
                    $container->addResource(new FileResource($file));
                }
            }

            $definition->addMethodCall('setRules', [RuleLoader::load($reflection)]);
        }
    }
}
