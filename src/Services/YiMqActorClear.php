<?php


namespace YiluTech\YiMQ\Services;


use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;
use YiluTech\YiMQ\Models\Message as MessageModel;
use YiluTech\YiMQ\Models\ProcessModel;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;

class YiMqActorClear
{
    public function run($context){

        try {
            //设置事务隔离为 读取未提交内容，查询message的时候，能查出创建还未保存的message
            \DB::select("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
            return $this->transactionStart($context);

        } finally {
            \DB::select("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        }


    }

    public function transactionStart($context){
        $result = \DB::transaction(function ()use($context){
            $failed_done_message_ids = $this->deleteMessages($context['done_message_ids'],MessageStatus::DONE);
            $failed_canceled_message_ids = $this->deleteMessages($context['canceled_message_ids'],MessageStatus::CANCELED);
            $failed_process_ids = $this->deleteProcesses($context['process_ids']);
            return [
                "message"=>'success',
                "failed_done_message_ids" => $failed_done_message_ids,
                "failed_canceled_message_ids" => $failed_canceled_message_ids,
                "failed_process_ids" => $failed_process_ids
            ];
        });
        return $result;
    }

    private function deleteProcesses($process_ids){
        if(count($process_ids) == 0){
            return [];
        }
        $process_ids_string = implode(',',$process_ids);
        $processTableName = (new ProcessModel())->getTable();
        $clearStatus = implode(',',[SubtaskStatus::DONE,SubtaskStatus::CANCELED]);
        $canClearProcesses = \DB::select("select * from $processTableName where id in (". $process_ids_string .") and status in (". $clearStatus .") for update skip locked");
        $canClearProcessIds = array_column($canClearProcesses,"id");
        //通过数组取差集得到在数据库不能删除的process,这里边包含 被锁定的、状态不对的，不存在的
        $failedClearProcessIds = array_diff($process_ids,$canClearProcessIds);

        //把$failedClearMessageIds 不带状态条件和for update 再查一次，排除已经被删除的message
        //(需要设置事务隔离为 Read Uncommitted，防止还未提交的message也被误判为已经删除)
        $failedClearProcessIds = ProcessModel::whereIn('id',$failedClearProcessIds)->get()->pluck('id')->toArray();
        ProcessModel::whereIn('id',$canClearProcessIds)->delete();
        return $failedClearProcessIds;
    }
    
    private function deleteMessages($message_ids,$status){
        if(count($message_ids) == 0){
            return [];
        }
        $message_ids_string =  implode(',',$message_ids);
        $messageTableName = (new MessageModel())->getTable();
        //查出未被锁定可以被删除的message
        $canClearMessages = \DB::select("select * from $messageTableName where message_id in (" .$message_ids_string. ") and status = ? for update skip locked",[$status]);
        $canClearMessageIds = array_column($canClearMessages,"message_id");
        //通过数组取差集得到在数据库不能删除的message,这里边包含 被锁定的、状态不对的，不存在的
        $failedClearMessageIds = array_diff($message_ids,$canClearMessageIds);
        //把$failedClearMessageIds 不带状态条件和for update 再查一次，排除已经被删除的message
        //(需要设置事务隔离为 Read Uncommitted，防止还未提交的message也被误判为已经删除)
        $failedClearMessageIds = MessageModel::whereIn('message_id',$failedClearMessageIds)->get()->pluck('message_id')->toArray();
//        if(count($failedClearMessageIds)){
//            \Log::error('YiMQ actor clear failedClearMessageIds:',$failedClearMessageIds);
//        }
        $this->deleteSubtasks($canClearMessageIds);
        MessageModel::whereIn('message_id',$canClearMessageIds)->delete();
        return $failedClearMessageIds;
    }
    private function deleteSubtasks($canClearMessageIds){
        SubtaskModel::whereIn('message_id',$canClearMessageIds)->delete();
    }

}