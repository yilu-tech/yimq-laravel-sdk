<?php


namespace YiluTech\YiMQ\Mock\Mockers;


use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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

    public function makeHttpRequestException(){
        return  new RequestException(
            'YiMQ call server failed.',
            new Request('POST',$this->client->actions['subtask']),
            new Response($this->statusCode,[],json_encode($this->data))
        );
    }

}