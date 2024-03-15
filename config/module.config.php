<?php
return [
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => OMEKA_PATH . '/modules/LocalContexts/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    // 'api_adapters' => [
    //     'invokables' => [
    //         'pid_items' => 'LocalContexts\Api\Adapter\PIDItemAdapter',
    //     ],
    // ],
    'controllers' => [
        'factories' => [
            'LocalContexts\Controller\Index' => 'LocalContexts\Service\Controller\IndexControllerFactory',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            OMEKA_PATH . '/modules/LocalContexts/view',
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            OMEKA_PATH . '/modules/LocalContexts/src/Entity',
        ],
        'proxy_paths' => [
            OMEKA_PATH . '/modules/LocalContexts/data/doctrine-proxies',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            'LocalContexts\Form\ProjectForm' => 'LocalContexts\Form\ProjectForm',
            'LocalContexts\Form\AssignForm' => 'LocalContexts\Form\AssignForm',
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'local-contexts' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/local-contexts',
                            'defaults' => [
                                '__NAMESPACE__' => 'LocalContexts\Controller',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'assign' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/assign',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'LocalContexts\Controller',
                                        'controller' => 'Index',
                                        'action' => 'assign',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Local Contexts', // @translate
                'route' => 'admin/local-contexts',
                'resource' => 'LocalContexts\Controller\Index',
            ],
        ],
    ],
];
