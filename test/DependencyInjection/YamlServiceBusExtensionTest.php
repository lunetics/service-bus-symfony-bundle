<?php
/**
 * prooph (http://getprooph.org/)
 *
 * @see       https://github.com/prooph/service-bus-symfony-bundle for the canonical source repository
 * @copyright Copyright (c) 2016 prooph software GmbH (http://prooph-software.com/)
 * @license   https://github.com/prooph/service-bus-symfony-bundle/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ProophTest\Bundle\ServiceBus\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class YamlServiceBusExtensionTest extends AbstractServiceBusExtensionTestCase
{
    protected function loadFromFile(ContainerBuilder $container, $file)
    {
        $loadYaml = new YamlFileLoader($container, new FileLocator(__DIR__.'/Fixture/config/yml'));
        $loadYaml->load($file.'.yml');
    }
}
