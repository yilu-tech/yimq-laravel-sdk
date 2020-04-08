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
    public $local_id;
    public $mockManager;
    public $delay = 2000;
    public $data;
    public function __construct(YiMqClient $client,$topic)
    {
        $this->client = $client;
        $this->mockManager = $client->getMockManager();
        $this->topic = $topic;
    }
    public function delay($millisecond){
        $this->delay = $millisecond;
        return $this;
    }
    public function data($data){
        $this->data = $data;
        return $this;
    }

    abstract function create();

    public function getTopic(){
        if(is_null($this->topic)){
            throw new \Exception('Topic not set.');
        }
        return $this->topic;
    }

}