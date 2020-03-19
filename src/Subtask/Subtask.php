<?php


namespace YiluTech\YiMQ\Subtask;


use YiluTech\YiMQ\Message\TransactionMessage;
use YiluTech\YiMQ\YiMqClient;
use YiluTech\YiMQ\YiMqMessageBuilder;

abstract class Subtask
{
    public $id;
    protected $client;
    public $processor;
    protected $message;
    protected $data;
    protected $mockManager;
    public $model;
    public function __construct(YiMqClient $client,TransactionMessage $message, $processor)
    {
        $this->client = $client;
        $this->mockManager = $client->getMockManager();
        $this->message = $message;
        $this->processor = $processor;


    }

    public function data($data):Subtask
    {
        $this->data = $data;
        return $this;
    }
    public function getData(){
        return $this->data;
    }
    abstract public function run();
}