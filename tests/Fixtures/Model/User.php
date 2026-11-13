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

namespace Silarhi\VoterBundle\Tests\Fixtures\Model;

use function assert;

use Override;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class User implements UserInterface
{
    public function __construct(
        private string $identifier,
        public ?Profile $profile = null,
    ) {
    }

    #[Override]
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * Symfony 7.4 still declares it; 8.0 removed it.
     */
    public function eraseCredentials(): void
    {
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        assert('' !== $this->identifier);

        return $this->identifier;
    }
}
