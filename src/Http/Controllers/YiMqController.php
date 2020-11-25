<?php


namespace YiluTech\YiMQ\Http\Controllers;


use Illuminate\Http\Request;
use YiluTech\YiMQ\Services\YiMqActorClear;
use YiluTech\YiMQ\Services\YiMqActorConfig;
use YiluTech\YiMQ\YiMqActor;

class YiMqController
{
    function run(Request $request,YiMqActor $yiMqActor,YiMqActorConfig $yiMqActorConfig,YiMqActorClear $yiMqActorClear){
        $action = $request->input('action');
        $context = $request->input('context');
//        if(env('YIMQ_ACTION_LOG',true)){
//            $context = is_array($context) ? $context : [$context];
//            \Log::info("YiMQ Action $action",$context);
//        }
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
            case 'ACTOR_CLEAR':
                return $yiMqActorClear->run($context);
            default:
                throw new \Exception("Action <$action> not exists.");

        }
    }
}