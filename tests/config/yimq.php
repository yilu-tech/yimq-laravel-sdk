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
        'user.xa.create' => \Tests\Services\UserXaCreateProcessor::class,
        'user.create.xa.child-transaction' => \Tests\Services\UserCreateXaChildTransactionProcessor::class,
        'user.tcc_create' => \Tests\Services\UserTccCreateProcessor::class,
        'user.tcc_create-child-transaction' => \Tests\Services\UserTccCreateChildTransaction::class,
        'user.ec.update' => \Tests\Services\UserEcUpdateProcessor::class,
        'user.update.ec.child-transaction' => \Tests\Services\UserUpdateEcChildTransactionProcessor::class,
        'user.exception' => \Tests\Services\ExceptionProcessor::class,

    ],
    /**
     * 消息事件监听器
     */
    'broadcast_topics' => [
        'user.xa.create' => [
            'allows'=>[]
        ]
    ],
    'broadcast_listeners'=>[
        \Tests\Services\UserUpdateListenerProcessor::class => 'user@user.ec.update',
    ]


];
