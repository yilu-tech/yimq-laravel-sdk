<?php


namespace YiluTech\YiMQ\Subtask;


use YiluTech\YiMQ\Message\TransactionMessage;
use YiluTech\YiMQ\YiMqClient;
use YiluTech\YiMQ\YiMqMessageBuilder;

abstract class ProcessorSubtask extends Subtask
{

    public $processor;


    public function __construct(YiMqClient $client,TransactionMessage $message, $processor)
    {
        parent::__construct($client, $message);
        $this->processor = $processor;


    }
}