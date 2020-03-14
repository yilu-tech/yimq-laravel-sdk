<?php


namespace YiluTech\YiMQ\Mock\Mockers;


use YiluTech\YiMQ\YiMqClient;

abstract class YiMqMocker
{
    public $client;
    public $mockManager;
    public $target;
    public function __construct(YiMqClient $client)
    {
        $this->client = $client;
        $this->mockManager = $this->client->getMockManager();


    }
    abstract public function getType();
    abstract public function checkConditions($object,$conditions);
    abstract public function run();
    public function setTarget($target){
        $this->target = $target;
    }

}