<?php


namespace YiluTech\YiMQ\Processor\BaseProcessor;


use YiluTech\YiMQ\Constants\ProcessStatus;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;
use YiluTech\YiMQ\Message\TransactionMessage;
use YiluTech\YiMQ\Models\ProcessModel;
abstract class Processor
{
    public $id;
    public $producer;
    protected $message_id;
    protected $processor;
    protected $data;
    protected $processModel;
    public $type;
    public $serverType;
    public $context;
    private $_validator;
    protected $statusMap = [
        ProcessStatus::PREPARING => 'PREPARING',
        ProcessStatus::PREPARED => 'PREPARED',
        ProcessStatus::DOING => 'DOING',
        ProcessStatus::DONE => 'DONE',
        ProcessStatus::CANCELLING => 'CANCELLING',
        ProcessStatus::CANCELED => 'CANCELED',
    ];
    public $childTransactionTopic = null;
    public $childTransaction = null;

    protected function setContextToThis($context){
        $this->id = $context['id'];
        $this->producer = isset($context['producer'])? $context['producer'] : 'test';
        $this->message_id = $context['message_id'];
        $this->processor = $context['processor'];
        $this->data = $context['data'];
        $this->context = $context;
    }

    public function subtaskMatchProcessor($contextType){
        if($this->serverType != $contextType){
            throw new YiMqSystemException("<".$this->serverType.">processor can not process  <".$contextType.">subtask.");
        }
    }
    protected function setAndlockSubtaskModel(){
//        $this->processModel =  ProcessModel::lockForUpdate()->find($this->id);
        $this->processModel =  ProcessModel::lock('for update nowait')->find($this->id);
        if(!isset($this->processModel)){
            throw new YiMqSystemException("ProcessorSubtask $this->id not exists");
        }
    }
    protected function _runValidate(){
        $this->validate(function ($rules){
            $this->_validator = \Validator::make($this->data, $rules);
            return $this->_validator;
        });
        $this->_validator->validate();
    }
    protected abstract function validate($validator);

    protected function createProcess($status){
        $subtaskModel = new ProcessModel();
        $subtaskModel->id = $this->id;
        $subtaskModel->producer = $this->producer;
        $subtaskModel->message_id = $this->message_id;
        $subtaskModel->type = $this->type;
        $subtaskModel->processor = $this->processor;
        $subtaskModel->data = $this->data;
        $subtaskModel->status = $status;
        $subtaskModel->save();
    }

    protected function setSubtaskStatusCanceled(){
        ProcessModel::where('id',$this->id)->update(['status'=>ProcessStatus::CANCELED]);
    }

    protected function beforeTransaction(){

    }

    protected function afterTransaction(){

    }
    protected function catchTransaction(){

    }
    public function runConfirm($context){
        $this->subtaskMatchProcessor($context['type']);
        return $this->_runConfirm($context);
    }
    abstract function _runConfirm($context);


    public function getOptions(){
        return [];
    }


    protected function childTransactionInit(){
        if(!$this->childTransactionTopic){
            return;
        }

        $this->childTransaction = \YiMQ::transaction($this->childTransactionTopic)->parentSubtask($this);
        $this->childTransaction($this->childTransaction);
        $this->childTransaction->create();
    }
    protected function childTransactionStart(){
        if($this->childTransaction){
            \YiMQ::transaction()->start();
        }
    }

    protected function childTransactionPrepare(){
        if($this->childTransaction){
            \YiMQ::transaction()->prepare();
        }
    }

    protected function childTransactionRestore($processModel,$messageStatus){
        if($this->childTransactionTopic){
            $this->childTransaction = \YiMQ::restoreByParentSubtask($processModel,$messageStatus);
        }
    }
    protected function childTransactionStatusTo($messageStatus){
        if($this->childTransaction){
            \YiMQ::transaction()->statusTo($messageStatus);
        }
    }

    protected function childTransactionRemoteCommit(){
        if($this->childTransaction){
            \YiMQ::transaction()->remoteCommit();
        }
    }

    protected function childTransactionRemoteRollback(){
        if($this->childTransaction){
            \YiMQ::transaction()->remoteRollback();
        }
    }

    public function childTransaction(TransactionMessage $transaction){

    }
}