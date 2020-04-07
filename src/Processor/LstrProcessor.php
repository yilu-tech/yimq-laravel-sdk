<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\ProcessModel;

abstract class LstrProcessor extends EcProcessor
{

    public $serverType = 'LSTR';
    public $type = SubtaskType::LSTR;

    public function getCondition(){
        return null;
    }

    public function getOptions()
    {
        return [
          'condition' => $this->getCondition()
        ];
    }

}