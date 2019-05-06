# Swagger Documentation Provider for Apigility

[![Build Status](https://secure.travis-ci.org/zfcampus/zf-apigility-documentation-swagger.svg?branch=master)](https://secure.travis-ci.org/zfcampus/zf-apigility-documentation-swagger)
[![Coverage Status](https://coveralls.io/repos/github/zfcampus/zf-apigility-documentation-swagger/badge.svg?branch=master)](https://coveralls.io/github/zfcampus/zf-apigility-documentation-swagger?branch=master)

## Introduction

This module provides Apigility the ability to show API documentation through a
[Swagger UI](http://swagger.io/).

The Swagger UI is immediately accessible after enabling this module at the URI path `/apigility/swagger`.

In addition to providing the HTML UI, this module also plugs into the main Apigility documentation
resource (at the path `/apigility/documentation`) in order to allow returning a documentation
payload in the `application/vnd.swagger+json` media type; this resource is what feeds the Swagger
UI. You can access this representation by passing the media type `application/vnd.swagger+json` for
the `Accept` header via the path `/apigility/documentation/:module/:service`.

## Requirements
  
Please see the [composer.json](composer.json) file.

## Installation

Run the following `composer` command:

```console
$ composer require zfcampus/zf-apigility-documentation-swagger
```

Alternately, manually add the following to your `composer.json`, in the `require` section:

```javascript
"require": {
    "zfcampus/zf-apigility-documentation-swagger": "^1.2"
}
```

And then run `composer update` to ensure the module is installed.

Finally, add the module name to your project's `config/application.config.php` under the `modules`
key:

```php
return [
    /* ... */
    'modules' => [
        /* ... */
        'ZF\Apigility\Documentation\Swagger',
    ],
    /* ... */
];
```

> ### zf-component-installer
>
> If you use [zf-component-installer](https://github.com/zendframework/zf-component-installer),
> that plugin will install zf-apigility-documentation-swagger as a module for you.

## Routes

### /apigility/swagger

Shows the Swagger UI JavaScript application.

### Assets: `/zf-apigility-documentation-swagger/`

Various CSS, images, and JavaScript libraries required to deliver the Swagger UI client
application.

## Configuration

### System Configuration

The following is required to ensure the module works within a ZF2 and/or Apigility-enabled
application:

```php
namespace ZF\Apigility\Documentation\Swagger;

return [
    'router' => [
        'routes' => [
            'zf-apigility' => [
                'child_routes' => [
                    'swagger' => [
                        'type' => 'segment',
                        'options' => [
                            'route'    => '/swagger',
                            'defaults' => [
                                'controller' => SwaggerUi::class,
                                'action'     => 'list',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'api' => [
                                'type' => 'segment',
                                'options' => [
                                    'route' => '/:api',
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                                'may_terminate' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'service_manager' => [
        'factories' => [
            SwaggerViewStrategy::class => SwaggerViewStrategyFactory::class,
        ],
    ],

    'controllers' => [
        'factories' => [
            SwaggerUi::class => SwaggerUiControllerFactory::class,
        ],
    ],

    'view_manager' => [
        'template_path_stack' => [
            'zf-apigility-documentation-swagger' => __DIR__ . '/../view',
        ],
    ],

    'asset_manager' => [
        'resolver_configs' => [
            'paths' => [
                __DIR__ . '/../asset',
            ],
        ],
    ],

    'zf-content-negotiation' => [
        'accept_whitelist' => [
            'ZF\Apigility\Documentation\Controller' => [
                0 => 'application/vnd.swagger+json',
            ],
        ],
        'selectors' => [
            'Documentation' => [
                ViewModel::class => [
                    'application/vnd.swagger+json',
                ],
            ],
        ],
    ],
];
```

### Module Documentation

Some information needed in the Swagger documentation cannot be retreieved from the standard API documentation module
but is taken from the module's `documentation.config.php` file instead. Extend these files with the following keys
to complete the Swagger JSON output:

```php
<?php
return [
    // these fields are directly merged into the Swagger JSON output and can provide/override
    // any property that is supported by the Swagger 2.0 Specification. See https://swagger.io/docs/specification/2-0/basic-structure/
    'ZF\\Apigility\\Documentation\\Swagger\\Api' => [
        'info' => [
            'title' => 'My API',
            'description' => '',
        ],
        'securityDefinitions' => [
            'basic-auth' => [
                'type' => 'basic',
            ],
            'application-http' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'Authorization',
            ],
        ],
    ],
    // Swagger properties merged into each Service definition
    'Api\\V1\\Rest\\Some\\Controller' => [
        // reference to a security definition and specify the requird scope (for oauth2)
        'security' => 'basic-auth',
        'scope' => [],
    ],
    'Api\\V1\\Rest\\Other\\Controller' => [
        'security' => 'application-http',
        'scope' => [],
        'collection' => [
            // describe supported query parmeters extracted from the 'collection_query_whitelist' config
            'query' => [
                'q' => [
                    'type' => 'string',
                    'description' => 'Search term for filtering',
                ],
            ],
        ],
    ],
];
```

## ZF Events

### Listeners

#### ZF\Apigility\Documentation\Swagger\Module

This listener is attached to the `MvcEvent::EVENT_RENDER` event at priority `100`.  Its purpose is
to conditionally attach a view strategy to the view system in cases where the controller response is
a `ZF\Apigility\Documentation\Swagger\ViewModel` view model (likely selected as the
content-negotiated view model based off of `Accept` media types).

## ZF Services

### View Models

#### ZF\Apigility\Documentation\Swagger\ViewModel

This view model is responsible for translating the available `ZF\Apigility\Documentation` models
into Swagger-specific models, and further casting them to arrays for later rendering as JSON.
