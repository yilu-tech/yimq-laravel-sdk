<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;

abstract class XaProcessor extends Processor
{
    public $type = SubtaskType::XA;
    private $pdo;
    public function __construct()
    {
        $this->pdo = \DB::connection()->getPdo();
    }

    public function runTry($context){
        $this->checkSubtaskType('XA',$context['type']);
        $this->setContextToThis($context);

        //1. 本地记录subtask
        $this->createProcess(SubtaskStatus::PREPARING);
        //TODO:: 如果子任务已经存在就不开启事务了
        //2. 开启xa事务
        $this->pdo->exec("set innodb_lock_wait_timeout=1");
        $this->pdo->exec("XA START '$this->id'");
        try{
            $this->setAndlockSubtaskModel();
            $this->pdo->exec("set innodb_lock_wait_timeout=5");
            $prepareResult = $this->prepare();
            $this->processModel->status = SubtaskStatus::DONE;
            $this->processModel->save();
            //3. prepare xa事务
            $this->pdo->exec("XA END '$this->id'");
            $this->pdo->exec("XA PREPARE '$this->id'");
            return $prepareResult;

        }catch (\Exception $e){
            $this->pdo->exec("XA END '$this->id'");
            $this->pdo->exec("XA ROLLBACK '$this->id'");
            throw $e;
        }


    }

    public function runConfirm($context){
        $this->id = $context['id'];
        try{
            $this->pdo->exec("XA COMMIT '$this->id'");
            return ['message'=>"succeed"];
        }catch (\Exception $e){
            if($e->getCode() != "XAE04"){
                throw  $e;
            }

            //如果不是xa id不存在，就锁定任务记录，判断状态是否已为done
            $this->setAndlockSubtaskModel();
            if($this->processModel->status == SubtaskStatus::DONE){
                return ['message'=>"retry_succeed"];
            }
            throw new YiMqSystemException("Status is not DONE.");
        }

    }

    public function runCancel($context){
        $this->id = $context['id'];
        try{
            $this->pdo->exec("XA ROLLBACK '$this->id'");
            $this->setSubtaskStatusCanceled();
            return ['message'=>"succeed"];
        }catch (\Exception $e){
            if($e->getCode() != "XAE04"){
                throw  $e;
            }

            //如果不是xa id不存在，就锁定任务记录，判断状态是否已为done
            $this->setAndlockSubtaskModel();
            if($this->processModel->status == SubtaskStatus::CANCELED){
                return ['message'=>"retry_succeed"];
            }
            if($this->processModel->status == SubtaskStatus::PREPARING){
                $this->setSubtaskStatusCanceled();
                return ['message'=>"retry_succeed"];
            }
            $status = $this->statusMap[$this->processModel->status];
            throw new YiMqSystemException("Status is $status.");
        }

    }



    abstract function prepare();

}