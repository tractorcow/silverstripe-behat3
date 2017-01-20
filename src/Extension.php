<?php

namespace SilverStripe\BehatExtension;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;

/*
 * This file is part of the SilverStripe\BehatExtension
 *
 * (c) Michał Ochman <ochman.d.michal@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SilverStripe extension for Behat class.
 *
 * Configured by adding `SilverStripe\BehatExtension\Extension` to your behat.yml
 *
 * @author Michał Ochman <ochman.d.michal@gmail.com>
 */
class Extension implements ExtensionInterface
{
    /**
    * Extension configuration ID.
    */
    const SILVERSTRIPE_ID = 'silverstripe_extension';


    /**
    * {@inheritDoc}
    */
    public function getConfigKey()
    {
        return self::SILVERSTRIPE_ID;
    }

    public function initialize(ExtensionManager $extensionManager)
    {
    }

    public function load(ContainerBuilder $container, array $config)
    {
        if (!isset($config['framework_path'])) {
            throw new \InvalidArgumentException('Specify `framework_path` parameter for silverstripe_extension');
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../../config/services'));
        $loader->load('silverstripe.yml');

        $behatBasePath = $container->getParameter('paths.base');
        $config['framework_path'] = realpath(sprintf(
            '%s%s%s',
            rtrim($behatBasePath, DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            ltrim($config['framework_path'], DIRECTORY_SEPARATOR)
        ));
        if (!file_exists($config['framework_path']) || !is_dir($config['framework_path'])) {
            throw new \InvalidArgumentException('Path specified as `framework_path` either doesn\'t exist or is not a directory');
        }

        $container->setParameter('behat.silverstripe_extension.framework_path', $config['framework_path']);
        $container->setParameter('behat.silverstripe_extension.admin_url', $config['admin_url']);
        $container->setParameter('behat.silverstripe_extension.login_url', $config['login_url']);
        $container->setParameter('behat.silverstripe_extension.screenshot_path', $config['screenshot_path']);
        $container->setParameter('behat.silverstripe_extension.ajax_timeout', $config['ajax_timeout']);
        if (isset($config['ajax_steps'])) {
            $container->setParameter('behat.silverstripe_extension.ajax_steps', $config['ajax_steps']);
        }
        if (isset($config['region_map'])) {
             $container->setParameter('behat.silverstripe_extension.region_map', $config['region_map']);
        }
        $container->setParameter('behat.silverstripe_extension.bootstrap_file', $config['bootstrap_file']);
    }

    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $corePass = new Compiler\CoreInitializationPass();
        $corePass->process($container);
    }

    public function configure(ArrayNodeDefinition $builder)
    {
        $builder->
            children()->
                scalarNode('framework_path')->
                    defaultValue('framework')->
                end()->
                scalarNode('screenshot_path')->
                    defaultNull()->
                end()->
                arrayNode('region_map')->
                    useAttributeAsKey('key')->
                    prototype('variable')->end()->
                end()->
                scalarNode('admin_url')->
                    defaultValue('/admin/')->
                end()->
                scalarNode('login_url')->
                    defaultValue('/Security/login')->
                end()->
                scalarNode('ajax_timeout')->
                    defaultValue(5000)->
                end()->
                scalarNode('bootstrap_file')->
                    defaultNull()->
                end()->
                arrayNode('ajax_steps')->
                    defaultValue(array(
                        'go to',
                        'follow',
                        'press',
                        'click',
                        'submit'
                    ))->
                    prototype('scalar')->
                end()->
            end()->
        end();
    }
}
