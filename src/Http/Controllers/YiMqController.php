<?php


namespace YiluTech\YiMQ\Http\Controllers;


use Illuminate\Http\Request;
use YiluTech\YiMQ\Services\YiMqActorConfig;
use YiluTech\YiMQ\YiMqActor;

class YiMqController
{
    function run(Request $request,YiMqActor $yiMqActor,YiMqActorConfig $yiMqActorConfig){
        $action = $request->input('action');
        $context = $request->input('context');
        switch ($action){
            case 'TRY':
                return $yiMqActor->try($context);
            case 'CONFIRM':
                return $yiMqActor->confirm($context);
            case 'CANCEL':
                return $yiMqActor->cancel($context);
            case 'MESSAGE_CHECK':
                return $yiMqActor->messageCheck($context);
            case 'GET_CONFIG':
                return $yiMqActorConfig->get();
            default:
                throw new \Exception("Action <$action> not exists.");

        }
    }
}