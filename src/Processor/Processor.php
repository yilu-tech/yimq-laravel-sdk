<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Models\ProcessModel;
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
        $this->subtaskModel =  ProcessModel::lockForUpdate()->find($this->id);
        if(!isset($this->subtaskModel)){
            abort(400,"Subtask $this->id not exists");
        }
    }

    protected function setSubtaskStatusCanceled(){
        ProcessModel::where('id',$this->id)->update(['status'=>SubtaskStatus::CANCELED]);
    }

    private function beforeTransaction(){

    }

    private function afterTransaction(){

    }
    abstract public function confirm($context);
}