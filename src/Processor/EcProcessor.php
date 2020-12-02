<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\ProcessStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Message\TransactionMessage;
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
        if(isset($subtaskModel) && $subtaskModel->status == ProcessStatus::DONE){
            return ['status'=>"retry_succeed"];
        }

        if(!isset($subtaskModel)){
            $this->createProcess(ProcessStatus::DOING);
        }
        $this->_runValidate();


        try{
            $this->childTransactionInit();
            \DB::beginTransaction();
            $this->_ec_begin();
            $this->childTransactionStart();

            $this->do();

            $this->_ec_done();
            $this->childTransactionPrepare();
            $this->childTransactionStatusTo(MessageStatus::DONE);

            \DB::commit();

            $this->childTransactionRemoteCommit();
        }catch (\Exception $e){
            \DB::rollBack();
            $this->childTransactionStatusTo(MessageStatus::CANCELED);
            $this->childTransactionRemoteRollback();
            throw $e;
        }

        return ['status'=>"succeed"];


    }


    private function _ec_begin(){
        $this->setAndlockSubtaskModel();
    }
    private  function _ec_done(){
        $this->processModel->status = ProcessStatus::DONE;
        $this->processModel->save();
    }

    abstract protected function do();
}