<?php

namespace Charcoal\FilePond\ServiceProvider;

use Charcoal\FilePond\Service\FilePondService;

// from 'pimple'
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * File Pond Service Provider
 */
class FilePondServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container A container instance.
     * @return void
     */
    public function register(Container $container)
    {
        /**
         * @param Container $container The Pimple DI container.
         * @return FilePondService
         */
        $container['file-pond/service'] = function (Container $container) {
            return new FilePondService($container);
        };
    }
}
