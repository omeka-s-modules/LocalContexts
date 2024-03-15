<?php
namespace LocalContexts\Service\Controller;

use LocalContexts\Controller\IndexController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        $client = $services->get('Omeka\HttpClient');
        $indexController = new IndexController($settings, $client);
        return $indexController;
    }
}
