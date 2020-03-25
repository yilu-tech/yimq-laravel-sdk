<?php


namespace YiluTech\YiMQ\Mock;


use YiluTech\YiMQ\Mock\Mockers\YiMqMocker;

class YiMqMockManager
{
    public $mockers = [];
    public function __construct($client)
    {
    }

    public function add(YiMqMocker $mocker){
        array_push($this->mockers,$mocker);
    }
    public function hasMocker($object,$conditions=null){

        if($this->getMocker($object,$conditions)){
            return true;
        }
        return false;
    }

    public function runMocker($object,$conditions=[]){
        return $this->getMocker($object,$conditions)->run();
    }

    public function getMocker($object,$conditions=[]){

        foreach($this->mockers as $mocker){
            if(get_class($object) == $mocker->getType() && $mocker->checkConditions($object,$conditions)){
                $mocker->setTarget($object);
                return $mocker;
            }

        }
        return null;
    }
}