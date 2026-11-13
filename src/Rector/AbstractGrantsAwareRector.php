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

namespace Silarhi\VoterBundle\Rector;

use function assert;

use Override;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Contract\Rector\RectorInterface;
use Rector\Rector\AbstractRector;
use Silarhi\VoterBundle\Attribute\Grants;

/**
 * Runs a Rector rule on the private methods of a class, except the #[Grants] ones: the voter calls them through
 * reflection, which Rector cannot see.
 *
 * The decorated rule only looks at private methods: the #[Grants] ones are protected while it runs, then private again.
 * Their bodies stay in the class, so the helpers they call are still used.
 *
 * @internal
 */
abstract class AbstractGrantsAwareRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    #[Override]
    final public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    #[Override]
    final public function refactor(Node $node): ?Node
    {
        assert($node instanceof Class_, 'getNodeTypes() only lists Class_');

        /** @var list<array{ClassMethod, int}> $hidden */
        $hidden = [];
        foreach ($node->getMethods() as $method) {
            if ($method->isPrivate() && $this->hasGrants($method)) {
                $hidden[] = [$method, $method->flags];
                $method->flags = ($method->flags & ~Modifiers::PRIVATE) | Modifiers::PROTECTED;
            }
        }

        try {
            $result = $this->getDecoratedRule()->refactor($node);
        } finally {
            foreach ($hidden as [$method, $flags]) {
                $method->flags = $flags;
            }
        }

        return $result instanceof Node ? $result : null;
    }

    abstract protected function getDecoratedRule(): RectorInterface;

    private function hasGrants(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($this->isName($attribute->name, Grants::class)) {
                    return true;
                }
            }
        }

        return false;
    }
}
