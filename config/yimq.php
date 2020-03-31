<?php

return [

    'actor_name' => 'xxx',
    'route' => [
        'prefix' => '',//url前缀
        'name' =>'internal@test.yimq',//路由名称
    ],
    'services' => [
        'default' =>[
            'uri' => env('YIMQ_DEFALUT_SERVICE_URI'),
            'headers'=>[
            ]
        ]
    ],

    'topics' => [
    ],
    /**
     * 处理器
     */
    'processors'=>[
    ],
    /**
     * 广播消息主题
     */
    'broadcast_topics' => [
    ],
    /**
     * 广播消息监听器注册
     */
    'broadcast_listeners'=>[
    ]


];
