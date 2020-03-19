<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;

abstract class EcProcessor extends Processor
{
    private $type = SubtaskType::EC;
    public function confirm($context)
    {
        $this->id = $context['subtask_id'];
        $this->message_id = $context['message_id'];
        $this->data = $context['data'];
        $subtaskModel =  SubtaskModel::find($this->id);
        //如果任务已经存在且已经完成
        if(isset($subtaskModel) && $subtaskModel->status == SubtaskStatus::DONE){
            return ['status'=>"retry_succeed"];
        }

        if(!isset($subtaskModel)){
            $this->recordSubtask();
        }

        try{
            \DB::beginTransaction();
            $this->setAndlockSubtaskModel();
            $this->do();
            $this->subtaskModel->status = SubtaskStatus::DONE;
            $this->subtaskModel->save();
            \DB::commit();
            return ['status'=>"succeed"];
        }catch (\Exception $e){
            \DB::rollBack();
            throw $e;
        }

    }

    private function recordSubtask(){
        $subtaskModel = new SubtaskModel();

        $subtaskModel->id = $this->id;
        $subtaskModel->message_id = $this->message_id;
        $subtaskModel->type = $this->type;
        $subtaskModel->data = $this->data;
        $subtaskModel->status = SubtaskStatus::DOING;
        $subtaskModel->save();
    }


    abstract protected function do();
}