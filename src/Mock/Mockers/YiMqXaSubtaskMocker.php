<?php


namespace YiluTech\YiMQ\Mock\Mockers;


class YiMqXaSubtaskMocker extends YiMqTccSubtaskMocker
{
    public function getType(){
        return \YiluTech\YiMQ\Subtask\XaSubtask::class;
    }
}