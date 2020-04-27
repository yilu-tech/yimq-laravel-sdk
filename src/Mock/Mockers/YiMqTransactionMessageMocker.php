<?php


namespace YiluTech\YiMQ\Mock\Mockers;


use YiluTech\YiMQ\Constants\TransactionMessageAction;
use YiluTech\YiMQ\Exceptions\YiMqHttpRequestException;
use YiluTech\YiMQ\Models\Subtask as SutaskModel;
use YiluTech\YiMQ\YiMqClient;
use YiluTech\YiMQ\Models\Message as MessageModel;
class YiMqTransactionMessageMocker extends YiMqMocker
{
    public $statusCode;
    public $data;
    public $topic;
    public $action;
    public function __construct(YiMqClient $client)
    {
        parent::__construct($client);
    }
    public function setTopic($topic){
        $this->topic = $topic;
        $this->action = TransactionMessageAction::BEGIN;
    }
    public function reply($statusCode){
        $this->statusCode = $statusCode;
        $this->mockManager->add($this);
    }
    public function getType(){
        return \YiluTech\YiMQ\Message\TransactionMessage::class;
    }


    public function prepare(){
        $this->action = TransactionMessageAction::PREPARE;
    }
    public function commit(){
        $this->action = TransactionMessageAction::COMMIT;
    }
    public function rollback(){
        $this->action = TransactionMessageAction::ROLLBACK;
    }

    public function checkConditions($object,$conditions=[])
    {

        if($this->action != $conditions['action']){
            return false;
        }

        if(in_array($this->action,[TransactionMessageAction::PREPARE,TransactionMessageAction::COMMIT,TransactionMessageAction::ROLLBACK]) ){
            return true;
        }

        if($this->action == TransactionMessageAction::BEGIN && $object->topic == $this->topic ){
            return true;
        }

        return false;
    }

    public function run()
    {
        switch ($this->statusCode){
            case 400:
                throw new YiMqHttpRequestException($this->makeHttpRequestException());
            case 500;
                throw new YiMqHttpRequestException($this->makeHttpRequestException());
        }

        switch ($this->action){
            case TransactionMessageAction::BEGIN:
                return $this->beginRun();
            case TransactionMessageAction::PREPARE:
                return $this->prepareRun();
            case TransactionMessageAction::COMMIT:
                return $this->commitRun();
            case TransactionMessageAction::ROLLBACK:
                return $this->rollbackRun();
        }

    }

    public function beginRun(){
        $first = MessageModel::query()->orderByDesc('id')->first();
        $index = $first ?  ++ $first->id : 1;
        return [
            'id' =>   $index,
            'status' => 'PENDING'
        ];
    }
    public function prepareRun(){
        $result['prepare_subtasks'] = [];
        $first = SutaskModel::query()->orderByDesc('id')->first();
        $index = $first ?  ++ $first->id : 1;
        foreach ($this->target->prepareSubtasks as $subtask) {
            $ecSubtask = (array)$subtask;
            $ecSubtask['id'] = $index++;
            array_push($result['prepare_subtasks'],$ecSubtask);
        }

        return $result;
    }

    public function commitRun(){
        $result['id'] = $this->target->id;
        return $result;
    }

    public function rollbackRun(){
        $result['id'] = $this->target->id;
        return $result;
    }
}