<?php


namespace YiluTech\YiMQ\Message;


use YiluTech\YiMQ\Constants\MessageServerType;
use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\MessageType;
use YiluTech\YiMQ\Constants\TransactionMessageAction;
use YiluTech\YiMQ\Models\Message As MessageModel;

class TransactionMessage extends Message
{
    public $tccSubtasks = [];
    public $ecSubtasks = [];
    public $prepared = false;
    public function begin():TransactionMessage
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
        array_push($this->ecSubtasks,$subtask);
    }

    private function prepare(){
        $context = [
            'message_id' => $this->id,
            'ec_subtasks' => []
        ];
        //如果没有ecSubtask就不发起远程调用
        if(count($this->ecSubtasks) == 0){
            $this->prepared = true;
            return $this;
        }
        foreach ($this->ecSubtasks as $subtask){
            $ecSubtask = [
                'processor' => $subtask->processor,
                'data' => $subtask->getData()
            ];
            array_push($context['ec_subtasks'],$ecSubtask);
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
        foreach ($this->ecSubtasks as $key => $ecSubtask ){
            $ecSubtask->id = $result['ec_subtasks'][$key]['id'];
            $ecSubtask->save();
        }
    }

    public function commit(){

        $this->prepare();
        $this->localCommmit();

        if(!$this->data['_remoteCommitFailed']){
            $this->remoteCommit();
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
        //TODO::添加一个mock锚点，测试rollback后修改message状态失败
        $this->model->status = MessageStatus::CANCELED;
        $this->model->save();
        if(!$this->data['_remoteCancelFailed']){
            $this->remoteRollback();
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