<?php


namespace YiluTech\YiMQ\Subtask;


use YiluTech\YiMQ\Constants\SubtaskType;

class XaSubtask extends TccSubtask
{
    public $serverType = 'XA';
    public $type = SubtaskType::XA;


}