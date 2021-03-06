<?php
/**
 * prooph (http://getprooph.org/)
 *
 * @see       https://github.com/prooph/service-bus-symfony-bundle for the canonical source repository
 * @copyright Copyright (c) 2016 prooph software GmbH (http://prooph-software.com/)
 * @license   https://github.com/prooph/service-bus-symfony-bundle/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Prooph\Bundle\ServiceBus\DependencyInjection;

use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\Plugin\Router\CommandRouter;
use Prooph\ServiceBus\Plugin\Router\EventRouter;
use Prooph\ServiceBus\Plugin\Router\QueryRouter;
use Prooph\ServiceBus\QueryBus;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Defines and load message bus instances.
 */
final class ProophServiceBusExtension extends Extension
{
    const AVAILABLE_BUSES = [
        'command' => CommandBus::class,
        'event' => EventBus::class,
        'query' => QueryBus::class,
    ];

    public function getNamespace()
    {
        return 'http://getprooph.org/schemas/symfony-dic/prooph';
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('service_bus.xml');

        foreach (self::AVAILABLE_BUSES as $type => $bus) {
            if (! empty($config[$type . '_buses'])) {
                $this->busLoad($type, $bus, $config[$type . '_buses'], $container, $loader);
            }
        }

        $this->addClassesToCompile([
            CommandBus::class,
            QueryBus::class,
            EventBus::class,
            CommandRouter::class,
            EventRouter::class,
            QueryRouter::class,
        ]);
    }

    /**
     * Loads bus configuration depending on type. For configuration examples, please take look at
     * test/DependencyInjection/Fixture/config files
     *
     * @param string $type
     * @param string $class
     * @param array $config
     * @param ContainerBuilder $container
     * @param XmlFileLoader $loader
     */
    private function busLoad(
        string $type,
        string $class,
        array $config,
        ContainerBuilder $container,
        XmlFileLoader $loader
    ) {
        // load specific bus configuration e.g. command_bus
        $loader->load($type . '_bus.xml');

        $serviceBuses = [];
        foreach (array_keys($config) as $name) {
            $serviceBuses[$name] = 'prooph_service_bus.' . $name;
        }
        $container->setParameter('prooph_service_bus.' . $type . '_buses', $serviceBuses);

        $def = $container->getDefinition('prooph_service_bus.' . $type . '_bus');
        $def->setClass($class);

        foreach ($config as $name => $options) {
            $this->loadBus($type, $name, $options, $container);
        }
    }

    /**
     * Initializes specific service bus class with plugins and routes. Each class dependency must be set via a container
     * or reference definition.
     *
     * @param string $type
     * @param string $name
     * @param array $options
     * @param ContainerBuilder $container
     * @throws \Symfony\Component\DependencyInjection\Exception\BadMethodCallException
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @throws \Prooph\Bundle\ServiceBus\Exception\RuntimeException
     */
    private function loadBus(string $type, string $name, array $options, ContainerBuilder $container)
    {
        $serviceBusId = 'prooph_service_bus.' . $name;
        $serviceBusDefinition = $container->setDefinition(
            $serviceBusId,
            new DefinitionDecorator('prooph_service_bus.' . $type . '_bus')
        );

        // define message factory
        $messageFactoryId = 'prooph_service_bus.message_factory.'.$name;
        $container->setDefinition(
                $messageFactoryId,
                new DefinitionDecorator($options['message_factory'])
            );

        // define message factory plugin
        $messageFactoryPluginId = 'prooph_service_bus.message_factory_plugin.'.$name;
        $messageFactoryPluginDefinition = new DefinitionDecorator('prooph_service_bus.message_factory_plugin');
        $messageFactoryPluginDefinition->setArguments([new Reference($messageFactoryId)]);
        $messageFactoryPluginDefinition->setPublic(true);

        $container->setDefinition(
                $messageFactoryPluginId,
                $messageFactoryPluginDefinition
            );

        // define router
        $routerId = null;
        if (! empty($options['router'])) {
            $routerId = 'prooph_service_bus.' . $name . '.router';
            $routerDefinition = new DefinitionDecorator($options['router']['type']);
            $routerDefinition->setArguments([$options['router']['routes'] ?? []]);
            $routerDefinition->setPublic(true);
            $container->setDefinition($routerId, $routerDefinition);
        }

        //Attach container plugin
        $containerPluginId = 'prooph_service_bus.container_plugin';
        $pluginIds = array_filter(array_merge($options['plugins'], [$containerPluginId, $messageFactoryPluginId, $routerId]));

        // Wrap the message bus creation into factory to call attachToMessageBus on the plugins
        $serviceBusDefinition
            ->setFactory([new Reference('prooph_service_bus.'.$type.'_bus_factory'), 'create'])
            ->setArguments(
                [
                    $container->getDefinition('prooph_service_bus.'.$type.'_bus')->getClass(),
                    new Reference('service_container'),
                    $pluginIds,
                ]
            );
    }
}
