<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;

abstract class Processor
{
    protected $id;
    protected $message_id;
    protected $data;
    protected $subtaskModel;

    public function run($context){
        $this->beforeTransaction();
        $this->afterTransaction();

    }
    protected function setAndlockSubtaskModel(){
        $this->subtaskModel =  SubtaskModel::lockForUpdate()->find($this->id);
        if(!isset($this->subtaskModel)){
            abort(400,"Subtask $this->id not exists");
        }
    }

    protected function setSubtaskStatusCanceled(){
        SubtaskModel::where('id',$this->id)->update(['status'=>SubtaskStatus::CANCELED]);
    }

    private function beforeTransaction(){

    }

    private function afterTransaction(){

    }
    abstract public function confirm($context);
}