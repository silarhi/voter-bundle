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

namespace Silarhi\VoterBundle\Voter;

use LogicException;
use Override;
use ReflectionClass;
use ReflectionMethod;
use Silarhi\VoterBundle\Exception\MissingArgumentException;
use Silarhi\VoterBundle\Rule\Rule;
use Silarhi\VoterBundle\Rule\RuleLoader;

use function sprintf;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A voter whose rules are the #[Grants] of its methods and class.
 *
 * Each security attribute has its own subjects: an attribute is voted on only with a subject its rule accepts, and
 * abstains otherwise. Before any rule, resolveContext() may deny the vote, or hand the methods the values they ask
 * for by type (declare their classes with #[ProvidesArgument]).
 *
 * @phpstan-import-type RuleShape from Rule
 *
 * @extends Voter<string, mixed>
 */
abstract class AbstractRuleVoter extends Voter
{
    /**
     * Rules read by reflection, for the voters built without the container.
     *
     * @var array<class-string, array<string, Rule>>
     */
    private static array $loadedRules = [];

    /**
     * @var array<string, Rule>|null
     */
    private ?array $rules = null;

    /**
     * Sets the rules compiled by the container.
     *
     * @internal called by the container, with the rules of RegisterRulesPass
     *
     * @param array<string, RuleShape> $rules
     */
    final public function setRules(array $rules): void
    {
        $this->rules = array_map(Rule::fromArray(...), $rules);
    }

    #[Override]
    final public function supportsAttribute(string $attribute): bool
    {
        return isset($this->getRules()[$attribute]);
    }

    #[Override]
    final public function supportsType(string $subjectType): bool
    {
        foreach ($this->getRules() as $rule) {
            if ($rule->supportsType($subjectType)) {
                return true;
            }
        }

        return false;
    }

    #[Override]
    final protected function supports(string $attribute, mixed $subject): bool
    {
        $rule = $this->getRules()[$attribute] ?? null;

        return null !== $rule && $rule->supportsType(get_debug_type($subject));
    }

    #[Override]
    final protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $rule = $this->getRules()[$attribute] ?? throw new LogicException(sprintf('"%s" has no rule for "%s".', static::class, $attribute));

        $context = $this->resolveContext($token);
        if (null === $context) {
            return false;
        }

        if (null === $rule->method) {
            return true;
        }

        $arguments = [];
        foreach ($rule->arguments as $argument) {
            $arguments[] = match ($argument) {
                Rule::SUBJECT => $subject,
                Rule::ATTRIBUTE => $attribute,
                Rule::TOKEN => $token,
                Rule::VOTE => $vote,
                default => $context[$argument] ?? throw new MissingArgumentException(sprintf('%s::%s() asks for %s, but %s::resolveContext() returned none: return it under the "%s" key.', $rule->class, $rule->method, $argument, static::class, $argument)),
            };
        }

        return $this->call($rule, $rule->method, $arguments);
    }

    /**
     * Denies before any rule (null), or returns the values the #[Grants] methods ask for, by the classes declared with
     * #[ProvidesArgument]. Overrides add to the context of their parent:
     *
     *     $context = parent::resolveContext($token);
     *
     *     return null === $context ? null : [User::class => $user] + $context;
     *
     * @return array<class-string, object>|null
     */
    protected function resolveContext(TokenInterface $token): ?array
    {
        return [];
    }

    /**
     * @param list<mixed> $arguments
     */
    private function call(Rule $rule, string $method, array $arguments): bool
    {
        // reflection reaches the private methods of the declaring class; RuleLoader checked their bool return type
        return true === (new ReflectionMethod($rule->class, $method))->invokeArgs($this, $arguments);
    }

    /**
     * @return array<string, Rule>
     */
    private function getRules(): array
    {
        return $this->rules ??= self::$loadedRules[static::class] ??= array_map(
            Rule::fromArray(...),
            RuleLoader::load(new ReflectionClass($this)),
        );
    }
}
