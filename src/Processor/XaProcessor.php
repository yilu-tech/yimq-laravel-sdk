<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;
use YiluTech\YiMQ\Models\ProcessModel;

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

        //2. 开启xa事务
        $this->pdo->exec("XA START '$this->id'");
        try{
            $this->setAndlockSubtaskModel();
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
            return ['status'=>"succeed"];
        }catch (\Exception $e){
            if($e->getCode() != "XAE04"){
                throw  $e;
            }

            //如果不是xa id不存在，就锁定任务记录，判断状态是否已为done
            $this->setAndlockSubtaskModel();
            if($this->processModel->status == SubtaskStatus::DONE){
                return ['status'=>"retry_succeed"];
            }
            abort(400,"Status is not DONE.");
        }

    }

    public function runCancel($context){
        $this->id = $context['id'];
        try{
            $this->pdo->exec("XA ROLLBACK '$this->id'");
            $this->setSubtaskStatusCanceled();
            return ['status'=>"succeed"];
        }catch (\Exception $e){
            if($e->getCode() != "XAE04"){
                throw  $e;
            }

            //如果不是xa id不存在，就锁定任务记录，判断状态是否已为done
            $this->setAndlockSubtaskModel();
            if($this->processModel->status == SubtaskStatus::CANCELED){
                return ['status'=>"retry_succeed"];
            }
            if($this->processModel->status == SubtaskStatus::PREPARING){
                $this->setSubtaskStatusCanceled();
                return ['status'=>"retry_succeed"];
            }
            $status = $this->statusMap[$this->processModel->status];
            abort(400,"Status is $status.");
        }

    }



    abstract function prepare();

}