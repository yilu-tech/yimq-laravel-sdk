<?php


namespace YiluTech\YiMQ\Subtask\BaseSubtask;


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
    public $options = [];

    public function __construct(YiMqClient $client,TransactionMessage $message)
    {
        $this->client = $client;
        $this->mockManager = $client->getMockManager();
        $this->message = $message;
    }

    /**
     * @param $data
     * @return $this
     */
    public function data($data)
    {
        $this->data = $data;
        return $this;
    }
    public function attempt($attempts){
        $this->options['attempts'] = $attempts;
        return $this;
    }

    public function getData(){
        return $this->data;
    }
//    abstract public function run();
//    abstract public function getContext();
}