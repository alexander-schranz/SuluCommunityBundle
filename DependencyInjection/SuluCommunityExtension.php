<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\CommunityBundle\DependencyInjection;

use Sulu\Bundle\CommunityBundle\Manager\CommunityManager;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages community bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class SuluCommunityExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        foreach ($config[Configuration::WEBSPACES] as $webspaceKey => $webspaceConfig) {
            // Set firewall by webspace key
            if ($webspaceConfig[Configuration::FIREWALL] === null) {
                $webspaceConfig[Configuration::FIREWALL] = $webspaceKey;
            }

            // Set role by webspace key
            if ($webspaceConfig[Configuration::ROLE] === null) {
                $webspaceConfig[Configuration::ROLE] = ucfirst($webspaceKey) . 'User';
            }

            $container->setDefinition(
                sprintf('sulu_community.%s.community_manager', $webspaceKey),
                new Definition(
                    CommunityManager::class,
                    [
                        $webspaceConfig,
                        $webspaceKey,
                        new Reference('doctrine.orm.entity_manager'),
                        new Reference('event_dispatcher'),
                        new Reference('security.token_storage'),
                        new Reference('sulu_security.token_generator'),
                        new Reference('sulu_core.webspace.webspace_manager'),
                        new Reference('sulu.repository.user'),
                        new Reference('sulu.repository.role'),
                        new Reference('sulu.repository.contact'),
                    ]
                )
            );
        }

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
        $loader->load('validator.xml');
    }
}
