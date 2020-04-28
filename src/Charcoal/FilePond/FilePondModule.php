<?php

namespace Charcoal\FilePond;

// from 'charcoal-app'
use Charcoal\App\App;
use Charcoal\App\Module\AbstractModule;

// from 'charcoal-contrib-filepond'
use Charcoal\FilePond\Action\RequestAction;
use Charcoal\FilePond\ServiceProvider\FilePondServiceProvider;

// from 'pimple'
use Pimple\Container;

// from 'Psr-7'
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * FilePond Module
 */
class FilePondModule extends AbstractModule
{
    /**
     * Setup the module's dependencies.
     *
     * @return AbstractModule
     * @throws RuntimeException When the server_end_point is not a string.
     */
    public function setup()
    {
        /** @var Container $container */
        $container = $this->app()->getContainer();

        $FilePondServiceProvider = new FilePondServiceProvider();
        $container->register($FilePondServiceProvider);

        $filePondConfig = $container['file-pond/config'];
        $this->setConfig($filePondConfig);

        $serverEndPoint = $this->config('server_end_point');

        if (!is_string($serverEndPoint)) {
            throw new RuntimeException(sprintf(
                'Invalid type for \'server_end_point\'. Expected \'string\', but got [%s] in [%s]',
                gettype($serverEndPoint),
                get_class($this)
            ));
        }

        $serverEndPoint = '/'.ltrim($serverEndPoint, '\/ ');

        $this->app()->group(rtrim($serverEndPoint, '/'), function (App $app) {
            $app->get('', function (Request $request, Response $response) {
                /** @var Container $container */
                $container = $this;
                $action = new RequestAction([
                    'logger'            => $container['logger'],
                    'filePondService'   => $container['file-pond/service'],
                ]);

                return $action($request, $response);
            });
            $app->post('', function (Request $request, Response $response) {
                /** @var Container $container */
                $container = $this;
                $action = new RequestAction([
                    'logger'            => $container['logger'],
                    'config'            => $container['file-pond/config'],
                    'filePondService'   => $container['file-pond/service'],
                ]);

                return $action($request, $response);
            });

            $app->delete('', function (Request $request, Response $response) {
                /** @var Container $container */
                $container = $this;
                $action = new RequestAction([
                    'logger'            => $container['logger'],
                    'config'            => $container['file-pond/config'],
                    'filePondService'   => $container['file-pond/service'],
                ]);

                return $action($request, $response);
            });
        });

        return $this;
    }
}
