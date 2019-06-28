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

/**
 * FilePond Module
 */
class FilePondModule extends AbstractModule
{
    // const APP_CONFIG = 'vendor/locomotivemtl/charcoal-contrib-filepond/config/config.json';

    /**
     * Setup the module's dependencies.
     *
     * @return AbstractModule
     */
    public function setup()
    {
        $container = $this->app()->getContainer();

        $this->app()->group('/file-pond', function (App $app) {
            $app->get('', function (Request $request, Response $response) {
                /** @var Container $container */
                $container = $this;
                $action = new RequestAction([
                    'logger' => $container['logger'],
                    'container' => $container,
                ]);

                return $action($request, $response);
            });

            $app->post('', function (Request $request, Response $response) {
                /** @var Container $container */
                $container = $this;
                $action = new RequestAction([
                    'logger' => $container['logger'],
                    'container' => $container,
                ]);

                return $action($request, $response);
            });

            $app->delete('', function (Request $request, Response $response) {
                /** @var Container $container */
                $container = $this;
                $action = new RequestAction([
                    'logger' => $container['logger'],
                    'container' => $container,
                ]);

                return $action($request, $response);
            });
        });

        $mailchimpServiceProvider = new FilePondServiceProvider();
        $container->register($mailchimpServiceProvider);

        return $this;
    }
}
