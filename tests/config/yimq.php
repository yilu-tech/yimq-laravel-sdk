<?php

return [

    'actor_name' => 'user',
    'route' => [
        'prefix' => '',
        'name' =>'internal@test.yimq',
    ],
    'services' => [
        'default' =>[
            'uri' => env('YIMQ_DEFALUT_SERVICE_URI'),
            'headers'=>[
            ]
        ]
    ],
    /**
     * 消息参与处理器
     */
    'processors'=>[
        'user.create' => \Tests\Services\UserCreate::class,
        'user.tcc_create' => \Tests\Services\UserTccCreate::class,
        'user.update' => \Tests\Services\UserUpdate::class,
        'user.update.ec.manual' => \Tests\Services\UserUpdateEcManual::class,

    ],
    /**
     * 消息事件监听器
     */
    'broadcast_topics' => [
        'user.create' => [
            'allows'=>[]
        ]
    ],
    'broadcast_listeners'=>[
        \Tests\Services\UserUpdateListener::class => 'user@user.update',
    ]


];
