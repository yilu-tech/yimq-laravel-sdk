<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\ProcessModel;
use YiluTech\YiMQ\Processor\BaseProcessor\Processor;

abstract class EcProcessor extends Processor
{

    public $serverType = 'EC';
    public $type = SubtaskType::EC;
    public function _runConfirm($context)
    {
        $this->setContextToThis($context);

        $subtaskModel =  ProcessModel::find($this->id);
        //如果任务已经存在且已经完成
        if(isset($subtaskModel) && $subtaskModel->status == SubtaskStatus::DONE){
            return ['status'=>"retry_succeed"];
        }

        if(!isset($subtaskModel)){
            $this->createProcess(SubtaskStatus::DOING);
        }

        try{
            \DB::beginTransaction();
            $this->setAndlockSubtaskModel();
            $this->do();
            $this->processModel->status = SubtaskStatus::DONE;
            $this->processModel->save();
            \DB::commit();
            return ['status'=>"succeed"];
        }catch (\Exception $e){
            \DB::rollBack();
            throw $e;
        }

    }


    abstract protected function do();
}