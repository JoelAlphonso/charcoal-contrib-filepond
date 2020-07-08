Charcoal FilePond
===============

[![License][badge-license]][charcoal-contrib-filepond]
[![Latest Stable Version][badge-version]][charcoal-contrib-filepond]
[![Code Quality][badge-scrutinizer]][dev-scrutinizer]
[![Coverage Status][badge-coveralls]][dev-coveralls]
[![Build Status][badge-travis]][dev-travis]

A [Charcoal][charcoal-app] service provider for FilePond.

[FilePond](https://pqina.nl/filepond/) is a JavaScript library that can upload anything you throw at it, optimizes images for faster uploads, and offers a great, accessible, silky smooth user experience.

This contrib act in fact as an upload server to go along with FilePond.

## Table of Contents

-   [Installation](#installation)
    -   [Dependencies](#dependencies)
-   [Service Provider](#service-provider)
    -   [Parameters](#parameters)
    -   [Services](#services)
-   [Configuration](#configuration)
-   [Usage](#usage)
-   [Development](#development)
    -  [API Documentation](#api-documentation)
    -  [Development Dependencies](#development-dependencies)
    -  [Coding Style](#coding-style)
-   [Credits](#credits)
-   [License](#license)



## Installation

The preferred (and only supported) method is with Composer:

```shell
$ composer require locomotivemtl/charcoal-contrib-filepond
```

Then add the module to your project's module list like so:

```json
{
    "modules": {
        "charcoal/file-pond/file-pond": {}
    }
}
```

### Dependencies

#### Required

-   [**PHP 5.6+**](https://php.net): _PHP 7_ is recommended.
-   ext-fileinfo: "*",
-   league/flysystem ^1.0,
-   locomotivemtl/charcoal-app >= 0.7



## Service Provider

Charcoal\FilePond\ServiceProvider\FilePondServiceProvider

The service provider is automatically instantiated by the module.

### Services

-   [FilePondService.php](src/Charcoal/FilePond/Service/FilePondService.php)
    FilePondService can be invoked with a server config ident to bind it to a server when needed : 
    
    ```php
    $action = new RequestAction([
        'logger'          => $container['logger'],
        'filePondService' => $container['file-pond/service']($serverIdent),
    ]);
    ```
    
-   [FilePondConfig.php](src/Charcoal/FilePond/FilePondConfig.php)
 


## Configuration

The configuration for FilePond can be found here : [FilePondConfig.php](src/Charcoal/FilePond/FilePondConfig.php).

It uses the config file [file-pond.json](config/file-pond.json) as default configset and can be overridden in your project's config.

**getServer($ident)** and **getServers()** can be called on the config to retrieve server configuration(s) : [ServerConfig.php](src/Charcoal/FilePond/ServerConfig.php)

The config defines a list of servers :

A server is a combination of an endpoint mapped with a filesystem and information regarding file paths.

### Server options.
| Option           | Description                                                                                                                                                                                                                                                                                     | Default value     |
|:-----------------|:------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|:------------------|
| route            | The server endpoint url for the front-end app. Feeds the __server__ option of FilePond.js                                                                                                                                                                                                       | /file-pond        |
| filesystem_ident | Which **Filesystem** to use. See [charcoal-app](https://github.com/locomotivemtl/charcoal-app/blob/e2e8af2eb6001da6d75ea100d191f73365f1ff77/src/Charcoal/App/ServiceProvider/FilesystemServiceProvider.php#L45).                                                                                | private           |
| upload_path      | The definitive upload path root for files once the submission is completed. This path can be overridden in the submission action. The Helper [FilePondAwareTrait](src/Charcoal/FilePond/Service/Helper/FilePondAwareTrait) will attempt to save the upload using the property's **upload_path** | uploads/file-pond |
| transfer_dir     | The temporary file-pond folder root. Used while processing front-end file upload. Files from this directory will be transferred to there final location after the submission is completed.                                                                                                      | tmp/file-pond     |



## Usage

Overrides the Module Config as needed by copying the [file-pond.json](config/file-pond.json) structure in your project's config file as so:

```JSON
{
    "contrib": {
        "file-pond": {
            "config": {
                "servers": {
                    "default": {
                        "route": "/file-pond",
                        "filesystem_ident": "private",
                        "upload_path": "uploads/file-pond",
                        "transfer_dir": "tmp/file-pond"
                    },
                    "s3": {
                        "route": "/file-pond/s3",
                        "filesystem_ident": "s3",
                        "upload_path": "uploads/file-pond",
                        "transfer_dir": "tmp/file-pond"
                    }
                }
            }
        }
    }
}
```

Set a **route** to give to the FilePond.js front-end framework. A slim route will automatically be created using the desired pattern.
Once that is done, configure the front-end file-pond module using the correct FilePond documentation.
-   [FilePond vanilla installation](https://pqina.nl/filepond/docs/patterns/installation/)
-   [FilePond vanilla documentation](https://pqina.nl/filepond/docs/patterns/api/filepond-object/#creating-a-filepond-instance)
-   [FilePond available frameworks](https://pqina.nl/filepond/docs/patterns/frameworks/introduction/)

The key part of this process is that when the form is submitted, the server handles the transferring of previously uploaded files to their final paths.
This allows to move uploads to folders that contains a user specific id and conceals the real path mapping of the server for security reasons.

You can use the [FilePondAwareTrait](src/Charcoal/FilePond/Service/Helper/FilePondAwareTrait) on the action controller to handle filepond transfers.
First set the required dependencies like that: 

```PHP
<?php

// From 'charcoal-contrib-filepond'
use Charcoal\FilePond\Service\Helper\FilePondAwareTrait;

/**
 * Create a new instructor.
 */
class SomeAction extends AbstractAction
{
    use FilePondAwareTrait;

    /**
     * Inject dependencies from a DI Container.
     *
     * @param Container $container A Pimple DI service container.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setFilePondService($container['file-pond/service']('private'));
    }
}
```

Then you can use the [handleTransfer()](https://github.com/locomotivemtl/charcoal-contrib-filepond/blob/e62ddc3e0443bfc830f009bb9213f4aa27571a78/src/Charcoal/FilePond/Service/Helper/FilePondAwareTrait.php#L54) method to facilitate the transfer upload process.


handleTransfer() for reference :
```PHP
/**
 * Attempts to handle the transfer of uploads given the proper parameters.
 * If possible, it will try to :
 * - fetch the upload path from the property.
 * - fetch the filesystem based on the public access of the property.
 * - Pass it through the best processor depending on wether their were ids passed or not.
 *
 * @param string|array|null             $ids        The uploaded files ids. Let to null to force $_FILES and $_POST.
 * @param string|PropertyInterface|null $property   The property ident or a property.
 * @param ModelInterface|string|null    $context    The context object as model or class ident.
 * @param string|null                   $pathSuffix Path suffix.
 * @return array
 */
protected function handleTransfer(
    $ids = null,
    $property = null,
    $context = null,
    $pathSuffix = null
)
```

Example of usage : 
```PHP
/** Transfer uploaded files */
$files = $this->handleTransfer(
    $submissionData['someProp'],
    'some_prop',
    $someModel,
    $someModel->property('id')->autoGenerate()
);
```


## Development

To install the development environment:

```shell
$ composer install
```

To run the scripts (phplint, phpcs, and phpunit):

```shell
$ composer test
```



### API Documentation

-   The auto-generated `phpDocumentor` API documentation is available at:  
    [https://locomotivemtl.github.io/charcoal-contrib-filepond/docs/master/](https://locomotivemtl.github.io/charcoal-contrib-filepond/docs/master/)
-   The auto-generated `apigen` API documentation is available at:  
    [https://codedoc.pub/locomotivemtl/charcoal-contrib-filepond/master/](https://codedoc.pub/locomotivemtl/charcoal-contrib-filepond/master/index.html)



### Development Dependencies

-   [php-coveralls/php-coveralls][phpcov]
-   [phpunit/phpunit][phpunit]
-   [squizlabs/php_codesniffer][phpcs]



### Coding Style

The charcoal-contrib-filepond module follows the Charcoal coding-style:

-   [_PSR-1_][psr-1]
-   [_PSR-2_][psr-2]
-   [_PSR-4_][psr-4], autoloading is therefore provided by _Composer_.
-   [_phpDocumentor_](http://phpdoc.org/) comments.
-   [phpcs.xml.dist](phpcs.xml.dist) and [.editorconfig](.editorconfig) for coding standards.

> Coding style validation / enforcement can be performed with `composer phpcs`. An auto-fixer is also available with `composer phpcbf`.



## Credits

-   [Locomotive](https://locomotive.ca/)



## License

Charcoal is licensed under the MIT license. See [LICENSE](LICENSE) for details.



[charcoal-contrib-filepond]:  https://packagist.org/packages/locomotivemtl/charcoal-contrib-filepond
[charcoal-app]:             https://packagist.org/packages/locomotivemtl/charcoal-app

[dev-scrutinizer]:    https://scrutinizer-ci.com/g/locomotivemtl/charcoal-contrib-filepond/
[dev-coveralls]:      https://coveralls.io/r/locomotivemtl/charcoal-contrib-filepond
[dev-travis]:         https://travis-ci.org/locomotivemtl/charcoal-contrib-filepond

[badge-license]:      https://img.shields.io/packagist/l/locomotivemtl/charcoal-contrib-filepond.svg?style=flat-square
[badge-version]:      https://img.shields.io/packagist/v/locomotivemtl/charcoal-contrib-filepond.svg?style=flat-square
[badge-scrutinizer]:  https://img.shields.io/scrutinizer/g/locomotivemtl/charcoal-contrib-filepond.svg?style=flat-square
[badge-coveralls]:    https://img.shields.io/coveralls/locomotivemtl/charcoal-contrib-filepond.svg?style=flat-square
[badge-travis]:       https://img.shields.io/travis/locomotivemtl/charcoal-contrib-filepond.svg?style=flat-square

[psr-1]:  https://www.php-fig.org/psr/psr-1/
[psr-2]:  https://www.php-fig.org/psr/psr-2/
[psr-3]:  https://www.php-fig.org/psr/psr-3/
[psr-4]:  https://www.php-fig.org/psr/psr-4/
[psr-6]:  https://www.php-fig.org/psr/psr-6/
[psr-7]:  https://www.php-fig.org/psr/psr-7/
[psr-11]: https://www.php-fig.org/psr/psr-11/
