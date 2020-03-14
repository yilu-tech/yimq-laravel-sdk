<?php


namespace YiluTech\YiMQ\Message;


use YiluTech\YiMQ\YiMqBuilder;
use YiluTech\YiMQ\YiMqClient;
use YiluTech\YiMQ\YiMqMessageBuilder;

abstract class Message
{
    protected $client;
    public $topic;
    public  $model;
    public $id;
    public $mockManager;
    public function __construct(YiMqClient $client,$topic)
    {
        $this->client = $client;
        $this->mockManager = $client->getMockManager();
        $this->topic = $topic;
    }

    abstract function create();

    public function getTopic(){
        if(is_null($this->topic)){
            throw new \Exception('Topic not set.');
        }
        return $this->topic;
    }

}