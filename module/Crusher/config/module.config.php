<?php
namespace Crusher;

use Laminas\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'crusher' => [
                'type'    => Segment::class,
                'options' => [ 
                        'route'    => '/crusher[/:action[/:id]]',
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
        'product' => [
            'type'    => Segment::class,
            'options' => [
                    'route'       => '/product[/:action][/:id]',
                    'constraints' => [
                            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            'id'     	 => '[a-zA-Z0-9_-]*',
                    ],        		
                    'defaults' => [
                            'controller' => Controller\ProductController::class,
                            'action'     => 'crushedpro',
                    ],
            ],

        ],
        // 'crusher' => [
        //     'type'    => Segment::class,
        //     'options' => [
        //             'route'       => '/crusher/crusher[/:action][/:id]',
        //             'constraints' => [
        //                     'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        //                     'id'     	 => '[a-zA-Z0-9_-]*',
        //             ],        		
        //             'defaults' => [
        //                     'controller' => Controller\CrusherController::class,
        //                     'action'     => 'crusher',
        //             ],
        //     ],

       
        //     'may_terminate' => true,
        //     'child_routes'  => [
        //         'defaults' => [
        //             'type'      => Segment::class,
        //             'options'   => [
        //                 'route' => '/[:controller[/:action][/:id]]',
        //                 'constraints' => [
        //                     'controller'  => '[a-zA-Z][a-zA-Z0-9_-]*',
        //                     'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
        //                     'id'      => '[0-9]*',
        //                 ],
        //                 'defaults' => [
        //                 ],
        //             ],
        //         ],
        //         'paginator' => [
        //             'type' => Segment::class,
        //             'options' => [
        //                 'route' => '/[page/:page]',
        //                 'constraints' => [
        //                     'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
        //                     'id'      => '[0-9]*', 
        //                 ],
        //                 'defaults' => [
        //                     '__NAMESPACE__' => 'Crusher\Controller',
        //                     'controller' => Controller\CrusherController::class,
        //                 ],
        //             ],
        //         ],
        //     ],
        // ],
//    'crusher' => [
//     'type'    => 'Segment',
//     'options' => [
//         'route'    => '/crusher[/:action[/:id]]',
//         'constraints' => [
//             'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
//             'id'      => '[a-zA-Z0-9_-]*',
//         ],
//         'defaults' => [
//             'controller' => Controller\MasterController::class,
//             'action'     => 'company',
//         ],
//     ],
// ],

        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
             __DIR__ . '/../view',
        ],
    ],
];
