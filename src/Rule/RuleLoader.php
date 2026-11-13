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

use function count;
use function in_array;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Attribute\ProvidesArgument;
use Silarhi\VoterBundle\Exception\InvalidRuleException;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;

use function sprintf;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Reads the #[Grants] of a voter into its rules, and refuses what the voter could not honor.
 *
 * The container runs it when it builds (RegisterRulesPass); a voter built without the container runs it once per
 * class.
 *
 * @phpstan-import-type RuleShape from Rule
 *
 * @internal
 */
final class RuleLoader
{
    private const UNSUPPORTED_TYPES = ['iterable', 'callable', 'void', 'never', 'true', 'false', 'self', 'static', 'parent'];

    /**
     * @param ReflectionClass<covariant object> $class
     *
     * @return array<string, RuleShape> the rules by security attribute
     *
     * @throws InvalidRuleException
     */
    public static function load(ReflectionClass $class): array
    {
        $voter = $class->getName();
        if (!$class->isSubclassOf(AbstractRuleVoter::class) || $class->isAbstract()) {
            throw new InvalidRuleException(sprintf('"%s" is not a concrete %s.', $voter, AbstractRuleVoter::class));
        }

        $provided = self::providedArguments($class);
        $rules = [];
        $declaredBy = [];

        foreach ($class->getAttributes(Grants::class) as $attribute) {
            $grants = $attribute->newInstance();
            if (null === $grants->subjects) {
                throw new InvalidRuleException(sprintf('The #[Grants("%s")] of class "%s" must list its subjects: a class-level grant has no $subject parameter to tell them. List null to vote without a subject.', $grants->attributes[0], $voter));
            }

            $subjects = self::classLevelSubjects($grants->subjects, $voter);
            foreach ($grants->attributes as $name) {
                self::add($rules, $declaredBy, $name, new Rule($voter, null, $subjects, []), sprintf('class "%s"', $voter));
            }
        }

        foreach ($class->getMethods() as $method) {
            $attributes = $method->getAttributes(Grants::class);
            if ([] === $attributes) {
                continue;
            }

            $where = sprintf('%s::%s()', $method->class, $method->name);
            [$subjects, $arguments] = self::signature($method, $provided, $where);

            foreach ($attributes as $attribute) {
                $grants = $attribute->newInstance();
                if (null !== $grants->subjects) {
                    throw new InvalidRuleException(sprintf('The #[Grants("%s")] of %s cannot list subjects: type its $subject parameter instead.', $grants->attributes[0], $where));
                }

                foreach ($grants->attributes as $name) {
                    self::add($rules, $declaredBy, $name, new Rule($method->class, $method->name, $subjects, $arguments), $where);
                }
            }
        }

        if ([] === $rules) {
            throw new InvalidRuleException(sprintf('"%s" extends %s but grants nothing: add #[Grants] to the methods that decide its attributes.', $voter, AbstractRuleVoter::class));
        }

        return array_map(static fn (Rule $rule): array => $rule->toArray(), $rules);
    }

    /**
     * @param array<string, Rule>   $rules
     * @param array<string, string> $declaredBy
     */
    private static function add(array &$rules, array &$declaredBy, string $attribute, Rule $rule, string $where): void
    {
        if (isset($declaredBy[$attribute])) {
            throw new InvalidRuleException(sprintf('"%s" is granted twice, by %s and %s: one method decides an attribute.', $attribute, $declaredBy[$attribute], $where));
        }

        $rules[$attribute] = $rule;
        $declaredBy[$attribute] = $where;
    }

    /**
     * The classes the voter and its parents provide, from their #[ProvidesArgument].
     *
     * @param ReflectionClass<covariant object> $class
     *
     * @return list<class-string>
     */
    private static function providedArguments(ReflectionClass $class): array
    {
        $provided = [];
        for ($current = $class; false !== $current; $current = $current->getParentClass()) {
            foreach ($current->getAttributes(ProvidesArgument::class) as $attribute) {
                foreach ($attribute->newInstance()->classes as $provides) {
                    if (!class_exists($provides) && !interface_exists($provides)) {
                        throw new InvalidRuleException(sprintf('The #[ProvidesArgument] of "%s" names "%s", which is not a class or an interface.', $current->getName(), $provides));
                    }

                    if (in_array($provides, [TokenInterface::class, Vote::class], true)) {
                        throw new InvalidRuleException(sprintf('The #[ProvidesArgument] of "%s" cannot provide %s: the methods already get it by type.', $current->getName(), $provides));
                    }

                    $provided[] = $provides;
                }
            }
        }

        return array_values(array_unique($provided));
    }

    /**
     * @param non-empty-list<class-string|null> $subjects
     *
     * @return list<string>
     */
    private static function classLevelSubjects(array $subjects, string $voter): array
    {
        $types = [];
        foreach ($subjects as $subject) {
            if (null === $subject) {
                $types[] = 'null';

                continue;
            }

            if (!class_exists($subject) && !interface_exists($subject)) {
                throw new InvalidRuleException(sprintf('The #[Grants] of class "%s" lists the subject "%s", which is not a class or an interface.', $voter, $subject));
            }

            $types[] = $subject;
        }

        return array_values(array_unique($types));
    }

    /**
     * @param list<class-string> $provided
     *
     * @return array{list<string>, list<string>} the subjects and the arguments of the method
     */
    private static function signature(ReflectionMethod $method, array $provided, string $where): array
    {
        if ($method->isStatic()) {
            throw new InvalidRuleException(sprintf('%s cannot be static: #[Grants] methods run on the voter.', $where));
        }

        $return = $method->getReturnType();
        if (!$return instanceof ReflectionNamedType || 'bool' !== $return->getName() || $return->allowsNull()) {
            throw new InvalidRuleException(sprintf('%s must declare a bool return type: true grants, false denies.', $where));
        }

        $subjects = ['null'];
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            $argument = self::argument($parameter, $provided, $where);
            if (in_array($argument, $arguments, true)) {
                throw new InvalidRuleException(sprintf('%s asks twice for %s.', $where, self::describe($argument)));
            }

            if (Rule::SUBJECT === $argument) {
                $subjects = self::subjects($parameter->getType(), $where);
            }

            $arguments[] = $argument;
        }

        return [$subjects, $arguments];
    }

    /**
     * @param list<class-string> $provided
     */
    private static function argument(ReflectionParameter $parameter, array $provided, string $where): string
    {
        $name = '$' . $parameter->getName();
        if ($parameter->isVariadic()) {
            throw new InvalidRuleException(sprintf('%s cannot have the variadic parameter %s.', $where, $name));
        }

        $type = $parameter->getType();
        if (Rule::SUBJECT === $name) {
            return Rule::SUBJECT;
        }

        if (Rule::ATTRIBUTE === $name) {
            if ($type instanceof ReflectionType && (!$type instanceof ReflectionNamedType || 'string' !== $type->getName() || $type->allowsNull())) {
                throw new InvalidRuleException(sprintf('The $attribute parameter of %s must be a string.', $where));
            }

            return Rule::ATTRIBUTE;
        }

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || in_array($type->getName(), self::UNSUPPORTED_TYPES, true)) {
            throw new InvalidRuleException(sprintf('The parameter %s of %s must be named $subject or $attribute, or typed with %s, ?%s or a class the voter provides (%s).', $name, $where, TokenInterface::class, Vote::class, self::describeProvided($provided)));
        }

        $class = $type->getName();
        if (TokenInterface::class === $class) {
            return Rule::TOKEN;
        }

        if (is_a($class, TokenInterface::class, true)) {
            throw new InvalidRuleException(sprintf('Type the parameter %s of %s with %s: the token can be any implementation.', $name, $where, TokenInterface::class));
        }

        if (Vote::class === $class) {
            if (!$type->allowsNull()) {
                throw new InvalidRuleException(sprintf('The parameter %s of %s must be a nullable ?%s: the access decision manager does not always pass one.', $name, $where, Vote::class));
            }

            return Rule::VOTE;
        }

        $matches = array_values(array_filter($provided, static fn (string $provides): bool => is_a($provides, $class, true)));
        if ([] === $matches) {
            throw new InvalidRuleException(sprintf('The parameter %s of %s asks for %s, which the voter does not provide: declare it with #[ProvidesArgument] on the voter or a parent, and return it from resolveContext(). Provided: %s. Name the parameter $subject if it is the subject.', $name, $where, $class, self::describeProvided($provided)));
        }

        if (count($matches) > 1) {
            throw new InvalidRuleException(sprintf('The parameter %s of %s asks for %s, which matches several provided classes (%s): type it with one of them.', $name, $where, $class, implode(', ', $matches)));
        }

        return $matches[0];
    }

    /**
     * @return list<string>
     */
    private static function subjects(?ReflectionType $type, string $where): array
    {
        if (!$type instanceof ReflectionType) {
            return [Rule::ANY];
        }

        if ($type instanceof ReflectionIntersectionType) {
            throw new InvalidRuleException(sprintf('The $subject parameter of %s cannot be an intersection type: a subject is matched against each type of a union.', $where));
        }

        $members = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        $subjects = [];
        foreach ($members as $member) {
            if (!$member instanceof ReflectionNamedType) {
                throw new InvalidRuleException(sprintf('The $subject parameter of %s cannot have an intersection in its type.', $where));
            }

            $name = $member->getName();
            if (Rule::ANY === $name) {
                return [Rule::ANY];
            }

            if (in_array($name, self::UNSUPPORTED_TYPES, true)) {
                throw new InvalidRuleException(sprintf('The $subject parameter of %s cannot be typed "%s": use a class, an interface, null, object, mixed or a scalar type.', $where, $name));
            }

            if (!$member->isBuiltin() && !class_exists($name) && !interface_exists($name)) {
                throw new InvalidRuleException(sprintf('The $subject parameter of %s is typed with "%s", which is not a class or an interface.', $where, $name));
            }

            $subjects[] = $name;
        }

        if ($type->allowsNull()) {
            $subjects[] = 'null';
        }

        return array_values(array_unique($subjects));
    }

    private static function describe(string $argument): string
    {
        return str_starts_with($argument, '$') ? $argument : 'a ' . $argument;
    }

    /**
     * @param list<class-string> $provided
     */
    private static function describeProvided(array $provided): string
    {
        return [] === $provided ? 'none' : implode(', ', $provided);
    }
}
