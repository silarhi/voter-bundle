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

namespace Silarhi\VoterBundle\Tests\Fixtures;

use function dirname;

use Override;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\PostVoter;
use Silarhi\VoterBundle\Tests\Fixtures\Voter\ProfileVoter;
use Silarhi\VoterBundle\VoterBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Framework + Security + the bundle, with two autoconfigured voters.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    #[Override]
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new VoterBundle(),
        ];
    }

    #[Override]
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/voter-bundle/cache/' . $this->getEnvironment();
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/voter-bundle/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $container->extension('security', [
            'providers' => ['memory' => ['memory' => null]],
            'firewalls' => ['main' => ['lazy' => true]],
        ]);

        $services = $container->services();
        $services->set(PostVoter::class)->autoconfigure();
        $services->set(ProfileVoter::class)->autoconfigure();
    }
}
