<?php

return [

    'actor_name' => 'YIMQ_ACTOR_NAME',
    'services' => [
        'default' =>[
            'uri' => env('YIMQ_DEFALUT_SERVICE_URI'),
            'headers'=>[
            ]
        ]
    ],

    'topics' => [
        'user.create'
    ],
    /**
     * 消息参与处理器
     */
    'processors'=>[
        'user.create' => \Tests\Services\UserCreate::class,
        'user.update' => \Tests\Services\UserUpdate::class
    ],
    /**
     * 消息事件监听器
     */
    'listeners'=>[

    ]


];
