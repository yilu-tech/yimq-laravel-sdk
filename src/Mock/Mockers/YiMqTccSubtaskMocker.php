<?php


namespace YiluTech\YiMQ\Mock\Mockers;


use YiluTech\YiMQ\Exceptions\YiMqHttpRequestException;
use YiluTech\YiMQ\YiMqClient;
use YiluTech\YiMQ\Models\Subtask as SutaskModel;
class YiMqTccSubtaskMocker extends YiMqMocker
{
    public $statusCode;
    public $data;
    public $processor;
    public function __construct(YiMqClient $client,$processor)
    {
        parent::__construct($client);
        $this->processor = $processor;
    }

    public function reply($statusCode,$data){
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->mockManager->add($this);
    }
    public function getType(){
        return \YiluTech\YiMQ\Subtask\TccSubtask::class;
    }

    public function checkConditions($object,$conditions=[])
    {
        if($object->processor == $this->processor){
            return true;
        }
        return false;
    }

    public function run()
    {
        switch ($this->statusCode){
            case 200:
                $first = SutaskModel::query()->orderByDesc('id')->first();
                $index = $first ?  ++ $first->id : 1;
                return [
                    'id' =>  $index ,
                    'prepareResult' => $this->data,
                    'status' => 'PREPARED'
                ];
            case 400:
                throw new YiMqHttpRequestException('YiMQ server 400 error.');
            case 500;
                throw new YiMqHttpRequestException('YiMQ server 500 error.');
        }
    }
}