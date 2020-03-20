<?php

return [

    'actor_name' => env('YIMQ_ACTOR_NAME'),
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
    ],
    /**
     * 消息事件监听器
     */
    'listeners'=>[

    ]


];
