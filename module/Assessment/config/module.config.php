<?php
namespace Assessment;

use Laminas\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'q' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/assessment/q[/:action[/:id]]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => Controller\AssessmentController::class,
                        'action'     => 'assessment',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'defaults' => [
                        'type'      => Segment::class,
                        'options'   => [
                            'route' => '/[:controller[/:action][/:id]]',
                            'constraints' => [
                                'controller'  => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'      => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id'          => '[0-9]*',
                            ],
                            'defaults' => [],
                        ],
                    ],
                    'paginator' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/[page/:page]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id'     => '[0-9]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Assessment\Controller',
                                'controller'    => Controller\AssessmentController::class,
                            ],
                        ],
                    ],
                ],
            ],
        //    'masters' => [
        //         'type' => Segment::class,
        //         'options' => [
        //             'route' => '/masters[/:action[/:id]]',
        //             'defaults' => [
        //                 'controller' => Controller\MasterController::class,
        //                 'action' => 'company',
        //                 'action' => 'vehicle',
        //             ],
        //         ],
            
        //    ],
            'masters' => [
                'type'    => 'Segment',
                'options' => [
                    'route'    => '/masters[/:action[/:id]]',
                    'constraints' => [
                        'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'      => '[a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => Controller\MasterController::class,
                        'action'     => 'company',
                    ],
                ],
            ],
                    
             'machinebd' => [
                'type'    => 'Segment',
                'options' => [
                    'route'    => '/machinebd[/:action[/:id]]',
                    'constraints' => [
                        'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'      => '[a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => Controller\MachinebdController::class,
                        'action'     => 'machinebd',
                    ],
                ],
            ],
			
			  'target' => [
                'type'    => 'Segment',
                'options' => [
                    'route'    => '/target[/:action[/:id]]',
                    'constraints' => [
                        'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'      => '[a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => Controller\TargetController::class,
                        'action'     => 'target',
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
