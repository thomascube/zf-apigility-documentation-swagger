<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

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
                            'json' => [
                                'type' => 'segment',
                                'options' => [
                                    'route' => '/:api[-v:version].json',
                                    'constraints' => [
                                        'api' => '[a-zA-Z][a-zA-Z0-9_.%]+',
                                    ],
                                    'defaults' => [
                                        'controller' => 'SwaggerJsonController',
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
            'SwaggerJsonController' => \ZF\Apigility\Documentation\ControllerFactory::class,
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
        'controllers' => [
            'SwaggerJsonController' => 'SwaggerJson',
        ],
        'accept_whitelist' => [
            'ZF\Apigility\Documentation\Controller' => [
                0 => 'application/vnd.swagger+json',
            ],
            'SwaggerJsonController' => [
                0 => '*/*',
            ],
        ],
        'selectors' => [
            'Documentation' => [
                ViewModel::class => [
                    'application/vnd.swagger+json',
                ],
            ],
            'SwaggerJson' => [
                ViewModel::class => [
                    '*/*',
                ],
            ],
        ],
    ],
];
