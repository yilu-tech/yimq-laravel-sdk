<?php

return [

    'actor_name' => 'user',
    'services' => [
        'default' =>[
            'uri' => env('YIMQ_DEFALUT_SERVICE_URI'),
            'headers'=>[
            ]
        ]
    ],

    'topics' => [
        'user.create' => [
            'broadcast' => true,
            'white_list' => []
        ]
    ]

];
