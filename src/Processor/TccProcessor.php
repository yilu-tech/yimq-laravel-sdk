<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;

abstract class TccProcessor extends Processor
{
    public $type = SubtaskType::TCC;
    public function runTry($context){
        $this->checkSubtaskType('TCC',$context['type']);
        $this->setContextToThis($context);

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

    abstract public function try();

    public function runConfirm($context)
    {
        $this->id = $context['subtask_id'];
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

    abstract public function confirm();

    public function runCancel($context)
    {
        $this->id = $context['subtask_id'];

        //2. 开启事务
        \DB::beginTransaction();
        $this->setAndlockSubtaskModel();



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

    abstract public function cancel();



}