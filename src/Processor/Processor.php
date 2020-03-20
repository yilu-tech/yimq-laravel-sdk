<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;
use YiluTech\YiMQ\Models\ProcessModel;
abstract class Processor
{
    protected $id;
    protected $message_id;
    protected $processor;
    protected $data;
    protected $processModel;
    public $type;
    protected $statusMap = [
        SubtaskStatus::PREPARING => 'PREPARING',
        SubtaskStatus::PREPARED => 'PREPARED',
        SubtaskStatus::DOING => 'DOING',
        SubtaskStatus::DONE => 'DONE',
        SubtaskStatus::CANCELLING => 'CANCELLING',
        SubtaskStatus::CANCELED => 'CANCELED',
    ];

    protected function setContextToThis($context){
        $this->id = $context['id'];
        $this->message_id = $context['message_id'];
        $this->processor = $context['processor'];
        $this->data = $context['data'];
    }

    protected function checkSubtaskType($currentType,$subtaskType){
        if($currentType != $subtaskType){
            throw new YiMqSystemException("<$currentType>processor can not process  <$subtaskType>subtask.");
        }
    }
    protected function setAndlockSubtaskModel(){
        $this->processModel =  ProcessModel::lockForUpdate()->find($this->id);
        if(!isset($this->processModel)){
            throw new YiMqSystemException("Subtask $this->id not exists");
        }
    }

    protected function createProcess($status){
        $subtaskModel = new ProcessModel();
        $subtaskModel->id = $this->id;
        $subtaskModel->message_id = $this->message_id;
        $subtaskModel->type = $this->type;
        $subtaskModel->processor = $this->processor;
        $subtaskModel->data = $this->data;
        $subtaskModel->status = $status;
        $subtaskModel->save();
    }

    protected function setSubtaskStatusCanceled(){
        ProcessModel::where('id',$this->id)->update(['status'=>SubtaskStatus::CANCELED]);
    }

    private function beforeTransaction(){

    }

    private function afterTransaction(){

    }
    abstract public function runConfirm($context);
}