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

namespace Silarhi\VoterBundle;

use Override;
use Silarhi\VoterBundle\DependencyInjection\RegisterRulesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class VoterBundle extends AbstractBundle
{
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // after the autoconfiguration, which tags the voters
        $container->addCompilerPass(new RegisterRulesPass());
    }
}
