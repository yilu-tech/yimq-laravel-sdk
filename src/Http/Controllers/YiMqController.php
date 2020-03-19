<?php


namespace YiluTech\YiMQ\Http\Controllers;


use Illuminate\Http\Request;
use YiluTech\YiMQ\YiMqActor;

class YiMqController
{
    function run(Request $request,YiMqActor $yiMqActor){
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
            default:
                throw new \Exception("Action <$action> not exists.");

        }
    }
}