<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskServerType;
use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;
use YiluTech\YiMQ\Models\ProcessModel;
use YiluTech\YiMQ\Processor\BaseProcessor\BaseTccProcessor;

abstract class TccProcessor extends BaseTccProcessor
{
    public $type = SubtaskType::TCC;
    public $serverType=SubtaskServerType::TCC;

    abstract public function try();
    abstract public function cancel();
    abstract public function confirm();



    public function _runTry($context){

        //1. 本地记录subtask
        $this->createProcess(SubtaskStatus::PREPARING);


        //2. 开启事务
        \DB::beginTransaction();
        $this->setAndlockSubtaskModel();
        try{
            $tryResult = $this->try();
            $this->processModel->try_result = $tryResult;
            $this->processModel->status = SubtaskStatus::PREPARED;
            $this->processModel->save();
            //3. commit事务
            \DB::commit();
            return $tryResult;

        }catch (\Exception $e){
            \DB::rollBack();
            throw $e;
        }
    }


    public function _runConfirm($context)
    {
        //2. 开启事务
        \DB::beginTransaction();
        $this->setAndlockSubtaskModel();

        //如果任务已经存在且已经完成
        if($this->processModel->status == SubtaskStatus::DONE){
            \DB::rollBack();
            return ['status'=>"retry_succeed"];
        }

        if($this->processModel->status != SubtaskStatus::PREPARED){
            \DB::rollBack();//必须手动回滚，否则单元测试无法连续执行
            $status = $this->statusMap[$this->processModel->status];
            abort(400,"Status is $status.");
        }


        try{
            $this->confirm();
            $this->processModel->status = SubtaskStatus::DONE;
            $this->processModel->save();
            //3. commit事务
            \DB::commit();
            return ['status'=>"succeed"];

        }catch (\Exception $e){
            \DB::rollBack();
            throw $e;
        }
    }



    public function _runCancel($context)
    {
        //2. 开启事务
        \DB::beginTransaction();
//        $this->setAndlockSubtaskModel();
        $this->processModel =  ProcessModel::lockForUpdate()->find($this->id);
        if (!$this->processModel) {
            \DB::rollBack();
            return ['message'=>"not_prepare"];
        }


        //如果任务已经取消
        if($this->processModel->status == SubtaskStatus::CANCELED){
            \DB::rollBack();
            return ['status'=>"retry_succeed"];
        }

        if($this->processModel->status != SubtaskStatus::PREPARED){
            $status = $this->statusMap[$this->processModel->status];
            \DB::rollBack();//必须手动回滚，否则单元测试无法连续执行
            abort(400,"Status is $status.");
        }



        try{
            $this->cancel();
            $this->processModel->status = SubtaskStatus::CANCELED;
            $this->processModel->save();
            //3. commit事务
            \DB::commit();
            return ['status'=>"succeed"];

        }catch (\Exception $e){
            \DB::rollBack();
            throw $e;
        }
    }





}