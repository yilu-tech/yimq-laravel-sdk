<?php


namespace YiluTech\YiMQ\Processor\BaseProcessor;


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
    public $serverType;
    public $context;
    private $_validator;
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
        $this->context = $context;
    }

    public function subtaskMatchProcessor($contextType){
        if($this->serverType != $contextType){
            throw new YiMqSystemException("<".$this->serverType.">processor can not process  <".$contextType.">subtask.");
        }
    }
    protected function setAndlockSubtaskModel(){
        $this->processModel =  ProcessModel::lockForUpdate()->find($this->id);
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
    public function runConfirm($context){
        $this->subtaskMatchProcessor($context['type']);
        return $this->_runConfirm($context);
    }
    abstract function _runConfirm($context);


    public function getOptions(){
        return [];
    }
}