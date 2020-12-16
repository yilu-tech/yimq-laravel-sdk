<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\SubtaskServerType;
use YiluTech\YiMQ\Constants\ProcessStatus;
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
        $this->createProcess(ProcessStatus::PREPARING);
        $this->childTransactionInit();//初始化子事务

        //2. 开启事务
        \DB::beginTransaction();
        $this->setAndlockSubtaskModel();
        try{
            $this->childTransactionStart();//开始子事务
            $tryResult = $this->try();
            $this->processModel->try_result = $tryResult;
            $this->processModel->status = ProcessStatus::PREPARED;
            $this->processModel->save();

            $this->childTransactionPrepare();//子事务prepare
            $this->childTransactionStatusTo(MessageStatus::PREPARED);//子事务本地状态改变为prepared
            //3. commit事务
            \DB::commit();
            \YiMQ::clearTransactionMessage();//清理全局事务
            return $tryResult;

        }catch (\Exception $e){
            \DB::rollBack();
            $this->childTransactionStatusTo(MessageStatus::CANCELED);//本地回滚子事务状态
            $this->childTransactionRemoteRollback();//远程回滚子事务
            throw $e;
        }
    }


    public function _runConfirm($context)
    {
        //2. 开启事务
        \DB::beginTransaction();
        $this->setAndlockSubtaskModel();

        //如果任务已经存在且已经完成
        if($this->processModel->status == ProcessStatus::DONE){
            \DB::rollBack();
            return ['status'=>"retry_succeed"];
        }

        if($this->processModel->status != ProcessStatus::PREPARED){
            \DB::rollBack();//必须手动回滚，否则单元测试无法连续执行
            $status = $this->statusMap[$this->processModel->status];
            abort(400,"Status is $status.");
        }


        try{
            $this->confirm();
            $this->processModel->status = ProcessStatus::DONE;
            $this->processModel->save();
            $this->childTransactionRestore($this->processModel,MessageStatus::PREPARED);//恢复子事务
            $this->childTransactionStatusTo(MessageStatus::DONE);//本地commit子事务
            //3. commit事务
            \DB::commit();
            $this->childTransactionRemoteCommit();//远程commit子事务
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
        //$this->setAndlockSubtaskModel();
        $this->processModel =  ProcessModel::lock('for update nowait')->find($this->id);
        //如果不存在，就返回not_prepare，让协调器通过(todo: 这里长期观察出现情况比较少的话，应该去掉这句判断，直接抛出错误)
        if (!$this->processModel) {
            \DB::rollBack();
            return ['message'=>"not_prepare"];
        }


        //如果任务已经取消
        if($this->processModel->status == ProcessStatus::CANCELED){
            \DB::rollBack();
            return ['status'=>"retry_succeed"];
        }

        if($this->processModel->status == ProcessStatus::PREPARING){
            $this->setSubtaskStatusCanceled();
            \DB::commit();
            return ['message'=>"compensate_canceled"];
        }

        if($this->processModel->status != ProcessStatus::PREPARED){
            $status = $this->statusMap[$this->processModel->status];
            \DB::rollBack();//必须手动回滚，否则单元测试无法连续执行
            throw new YiMqSystemException("Status is $status.");
        }



        try{
            $this->cancel();
            $this->processModel->status = ProcessStatus::CANCELED;
            $this->processModel->save();
            $this->childTransactionRestore($this->processModel,MessageStatus::PREPARED);//恢复子事务
            $this->childTransactionStatusTo(MessageStatus::CANCELED);//本地回滚子事务状态
            //3. commit事务
            \DB::commit();
            $this->childTransactionRemoteRollback();//远程回滚子事务
            return ['status'=>"succeed"];

        }catch (\Exception $e){
            \DB::rollBack();
            throw $e;
        }
    }





}