<?php


namespace YiluTech\YiMQ\Message;


use YiluTech\YiMQ\Constants\MessageServerType;
use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\MessageType;
use YiluTech\YiMQ\Constants\TransactionMessageAction;
use YiluTech\YiMQ\Models\Message As MessageModel;
use YiluTech\YiMQ\YiMqClient;

class TransactionMessage extends Message
{
    public $tccSubtasks = [];
    public $prepareSubtasks = [];
    public $prepared = false;
    public $callback = null;
    public function __construct(YiMqClient $client, $topic,$callback)
    {
        parent::__construct($client, $topic);
        $this->callback = $callback;
    }

    public function begin(){
        if(is_null($this->callback)){
            return $this->_begin();
        }
        $this->_begin();
        try {
            $result = call_user_func($this->callback);
            $this->commit();
            return $result;
        }catch (\Exception $e){
            $this->rollback();
            throw $e;
        };
    }

    private function _begin():TransactionMessage
    {
        if( $this->client->hasTransactionMessage()){
            throw new \Exception("MicroApi transaction message already exists.");
        }
        $this->create();
        $this->client->setTransactionMessage($this);
        return  $this;
    }

    function create()
    {
        //1. 向协调器注册事物
        $messageInfo = $this->createRemoteTransactionRecrod();
        $this->id = $messageInfo['id'];
        //2. 本地数据库记录事物
        $this->createLocalTransactionRecord($messageInfo);
        //3. 开启事务
        \DB::beginTransaction();
        //4: 锁定message
        $this->model = MessageModel::lockForUpdate()->find($this->id);
    }


    private function createRemoteTransactionRecrod(){
        $context = [
            'topic' => $this->getTopic(),
            'type' => MessageServerType::TRANSACTION,
            'data' => $this->data,
            'delay' => $this->delay
        ];
        $mockConditions['action'] = TransactionMessageAction::BEGIN;
        if($this->mockManager->hasMocker($this,$mockConditions)){//TODO 增加一个test环境生效的判断
            return $this->mockManager->runMocker($this,$mockConditions);
        }else{
            return $this->client->callServer('create',$context);
        }

    }
    private function createLocalTransactionRecord($messageInfo){
        \Log::debug('TransactionMessage ['.$this->client->serviceName.'] -向本地数据库记录事物 '. $messageInfo['id']);

        $messageModel = new MessageModel();
        $messageModel->id = $messageInfo['id'];
        $messageModel->status = MessageStatus::PENDING;
        $messageModel->type = MessageType::TRANSACTION;
        $messageModel->topic = $this->topic;
        $messageModel->save();
    }
    public function addTccSubtask($subtask){
        array_push($this->tccSubtasks,$subtask);
    }
    public function addEcSubtask($subtask){
        array_push($this->prepareSubtasks,$subtask);
    }

    private function prepare(){
        $context = [
            'message_id' => $this->id,
            'prepare_subtasks' => []
        ];
        //如果没有ecSubtask就不发起远程调用
        if(count($this->prepareSubtasks) == 0){
            $this->prepared = true;
            return $this;
        }
        foreach ($this->prepareSubtasks as $subtask){
            $prepareSubtask = $subtask->getContext();
            array_push($context['prepare_subtasks'],$prepareSubtask);
        }
        $mockConditions['action'] = TransactionMessageAction::PREPARE;
        if($this->mockManager->hasMocker($this,$mockConditions)){//TODO 增加一个test环境生效的判断
            $result = $this->mockManager->runMocker($this,$mockConditions);
        }else{
            $result = $this->client->callServer('prepare',$context);
        }
        $this->preparedSaveToDb($result);
        $this->prepared = true;
        return $this;
    }
    private function preparedSaveToDb($result){
        foreach ($this->prepareSubtasks as $key => $subtask ){
            $subtask->id = $result['prepare_subtasks'][$key]['id'];
            $subtask->save();
        }
    }

    public function commit(){

        $this->prepare();
        $this->localCommmit();
        //本地commit后，如果远程commit错误，忽略错误,让服务端回查来状态来确认
        try {
            $this->remoteCommit();
        }catch (\Exception $e){
            \Log::error($e);
        }


        return $this;
    }

    private function localCommmit(){
        $this->model->status = MessageStatus::DONE;
        $this->model->save();
        \DB::commit();
    }
    private function remoteCommit(){
        $context['message_id'] = $this->id;
        $mockConditions['action'] = TransactionMessageAction::COMMIT;
        if($this->mockManager->hasMocker($this,$mockConditions)){//TODO 增加一个test环境生效的判断
            $result = $this->mockManager->runMocker($this,$mockConditions);
        }else{
            $result = $this->client->callServer('confirm',$context);
        }
    }

    public function rollback(){
        \DB::rollBack();
        //本地rollback后，如果远程commit错误，忽略错误,让服务端回查来状态来确认
        try {
            //TODO::添加一个mock锚点，测试rollback后修改message状态失败
            $this->model->status = MessageStatus::CANCELED;
            $this->model->save();
            $this->remoteRollback();
        }catch (\Exception $e){
            \Log::error($e);
        }

        return $this;
    }
    private function remoteRollback(){
        $context['message_id'] = $this->id;
        $mockConditions['action'] = TransactionMessageAction::ROLLBACK;
        if($this->mockManager->hasMocker($this,$mockConditions)){//TODO 增加一个test环境生效的判断
            $result = $this->mockManager->runMocker($this,$mockConditions);
        }else{
            $result = $this->client->callServer('cancel',$context);
        }

    }


}