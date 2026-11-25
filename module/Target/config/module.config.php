<?php
namespace Target;

use Laminas\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'target' => [
                'type'    => Segment::class,
                'options' => [ 
                        'route'    => '/target[/:action[/:id]]',
                        'constraints' => [
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id'     	 => '[a-zA-Z0-9_-]*',
                        ],
                        'defaults' => [
                                'controller' => Controller\IndexController::class,
                                'action'   => 'index',
                        ],
                ],
        ],
        'target' => [
            'type'    => Segment::class,
            'options' => [
                    'route'       => '/target[/:action][/:id]',
                    'constraints' => [
                            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            'id'     	 => '[a-zA-Z0-9_-]*',
                    ],        		
                    'defaults' => [
                            'controller' => Controller\TargetController::class,
                            'action'     => 'target',
                    ],
            ],

        ],
        'tcrusher' => [
                'type'    => Segment::class,
                'options' => [
                        'route'       => '/tcrusher[/:action][/:id]',
                        'constraints' => [
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id'     	 => '[a-zA-Z0-9_-]*',
                        ],        		
                        'defaults' => [
                                'controller' => Controller\TcrusherController::class,
                                'action'     => 'tcrusher',
                        ],
                ],
    
            ],
            'tfinance' => [
                'type'    => Segment::class,
                'options' => [
                        'route'       => '/tfinance[/:action][/:id]',
                        'constraints' => [
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id'     	 => '[a-zA-Z0-9_-]*',
                        ],        		
                        'defaults' => [
                                'controller' => Controller\TfinanceController::class,
                                'action'     => 'tfinance',
                        ],
                ],
    
            ],


        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
             __DIR__ . '/../view',
        ],
    ],
];
