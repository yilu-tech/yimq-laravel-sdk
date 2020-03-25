<?php


namespace YiluTech\YiMQ\Subtask;


use YiluTech\YiMQ\Message\TransactionMessage;
use YiluTech\YiMQ\YiMqClient;
use YiluTech\YiMQ\YiMqMessageBuilder;

abstract class Subtask
{
    public $serverType;
    public $type;
    public $id;
    protected $client;
    protected $message;
    protected $data;
    protected $mockManager;
    public $model;

    public function __construct(YiMqClient $client,TransactionMessage $message)
    {
        $this->client = $client;
        $this->mockManager = $client->getMockManager();
        $this->message = $message;
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
    abstract public function getContext();
}