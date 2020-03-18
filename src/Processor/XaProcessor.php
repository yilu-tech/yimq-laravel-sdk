<?php


namespace YiluTech\YiMQ\Processor;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;

abstract class XaProcessor extends Processor
{

    private $type = SubtaskType::XA;
    private $pdo;
    public function __construct()
    {
        $this->pdo = \DB::connection()->getPdo();
    }

    public function run($context){
        $this->id = $context['id'];
        $this->message_id = $context['message_id'];
        $this->data = $context['data'];
        //1. 本地记录subtask
        $this->recordSubtask();

        //2. 开启xa事务
        $this->pdo->exec("XA START '$this->id'");
        $this->setAndlockSubtaskModel();

        //2. 开启xa事务
        try{
            $this->prepare();
            $this->subtaskModel->status = SubtaskStatus::DONE;
            $this->subtaskModel->save();
            //3. prepare xa事务
            $this->pdo->exec("XA END '$this->id'");
            $this->pdo->exec("XA PREPARE '$this->id'");

        }catch (\Exception $e){
            $this->pdo->exec("XA END '$this->id'");
            $this->pdo->exec("XA ROLLBACK '$this->id'");
            throw $e;
        }


    }

    private function recordSubtask(){
        $subtaskModel = new SubtaskModel();
        $subtaskModel->id = $this->id;
        $subtaskModel->message_id = $this->message_id;
        $subtaskModel->type = $this->type;
        $subtaskModel->data = $this->data;
        $subtaskModel->status = SubtaskStatus::PREPARING;
        $subtaskModel->save();
        $this->subtaskModel = $subtaskModel;
    }

    public function confirm($context){
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
            if($this->subtaskModel->status == SubtaskStatus::DONE){
                return ['status'=>"retry_succeed"];
            }
            abort(400,"Status is not DONE.");
        }

    }

    public function CANCEL($context){
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
            if($this->subtaskModel->status == SubtaskStatus::CANCELED){
                return ['status'=>"retry_succeed"];
            }
            if($this->subtaskModel->status == SubtaskStatus::PREPARING){
                $this->setSubtaskStatusCanceled();
                return ['status'=>"retry_succeed"];
            }
            abort(400,"Status is not PREPARING or CANCELED.");
        }

    }



    abstract function prepare();

}