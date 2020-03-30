<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\ProcessModel;

abstract class BcstProcessor extends EcProcessor
{

    public $serverType = 'BCST';
    public $type = SubtaskType::BCST;

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