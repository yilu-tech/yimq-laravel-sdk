<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\SubtaskServerType;
use YiluTech\YiMQ\Constants\ProcessStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;
use YiluTech\YiMQ\Models\ProcessModel;
use YiluTech\YiMQ\Processor\BaseProcessor\BaseTccProcessor;

abstract class XaProcessor extends BaseTccProcessor
{
    public $type = SubtaskType::XA;
    private $pdo;
    public $serverType = SubtaskServerType::XA;
    public function __construct()
    {
        $this->pdo = \DB::connection()->getPdo();
    }

    public function _runTry($context){
        //1. 本地记录subtask
        $this->createProcess(ProcessStatus::PREPARING);
        $this->beforeTransaction();
        $this->childTransactionInit();
        //TODO:: 如果子任务已经存在就不开启事务了
        //2. 开启xa事务
        $this->pdo->exec("XA START '$this->id'");
        try{
            $this->childTransactionStart();
            $this->setAndlockSubtaskModel();

            $prepareResult = $this->prepare();//执行业务

            $this->processModel->status = ProcessStatus::DONE;
            $this->processModel->save();
            $this->childTransactionPrepare();
            $this->childTransactionStatusTo(MessageStatus::DONE);
            //3. prepare xa事务
            $this->pdo->exec("XA END '$this->id'");
            $this->pdo->exec("XA PREPARE '$this->id'");
            $this->afterTransaction();
            \YiMQ::clearTransactionMessage();//清理全局事务
            return $prepareResult;

        }catch (\Exception $e){
            $this->pdo->exec("XA END '$this->id'");
            $this->pdo->exec("XA ROLLBACK '$this->id'");
            $this->childTransactionStatusTo(MessageStatus::CANCELED);
            $this->childTransactionRemoteRollback();
            $this->catchTransaction();
            \YiMQ::clearTransactionMessage();//清理全局事务
            throw $e;
        }


    }

    public function _runConfirm($context){
        try{
            $this->pdo->exec("XA COMMIT '$this->id'");
            $this->setAndlockSubtaskModel();
            //因为xa在事务中已经修改message状态为done，这里恢复状态为done的子任务
            $this->childTransactionRestore($this->processModel,MessageStatus::DONE);
            $this->childTransactionRemoteCommit();
            return ['message'=>"succeed"];
        }catch (\Exception $e){
            if(!$this->isUnknownXidException($e)){
                throw  $e;
            }
            //如果不是xa id不存在，就锁定任务记录，判断状态是否已为done
            $this->setAndlockSubtaskModel();
            if($this->processModel->status == ProcessStatus::DONE){
                return ['message'=>"retry_succeed"];
            }
            $status = $this->statusMap[$this->processModel->status];
            throw new YiMqSystemException("Status is $status.");
        }
    }

    public function _runCancel($context){
        try{
            $this->pdo->exec("XA ROLLBACK '$this->id'");

            $this->setSubtaskStatusCanceled();

            $this->setAndlockSubtaskModel();
            $this->childTransactionRestore($this->processModel,MessageStatus::PENDING);
            $this->childTransactionStatusTo(MessageStatus::CANCELED);
            $this->childTransactionRemoteRollback();

            return ['message'=>"canceled"];
        }catch (\Exception $e){
            if(!$this->isUnknownXidException($e)){
                throw  $e;
            }
            //如果不是xa id不存在，就锁定任务记录，判断状态是否已为done
            //$subTask = $this->setAndlockSubtaskModel();
            $this->processModel =  ProcessModel::lock('for update nowait')->find($this->id);
            if (!$this->processModel) {
                return ['message'=>"not_prepare"];
            }

            if($this->processModel->status == ProcessStatus::CANCELED){
                return ['message'=>"retry_canceled"];
            }
            if($this->processModel->status == ProcessStatus::PREPARING){
                $this->setSubtaskStatusCanceled();
                return ['message'=>"compensate_canceled"];
            }
            $status = $this->statusMap[$this->processModel->status];
            throw new YiMqSystemException("Status is $status.");
        }
    }

    private function isUnknownXidException($exception){
        return $exception->getCode() === "XAE04" ? true : false;
    }

    abstract function prepare();

}