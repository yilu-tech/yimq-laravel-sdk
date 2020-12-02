<?php


namespace YiluTech\YiMQ\Services;


use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\ProcessStatus;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;
use YiluTech\YiMQ\Models\Message as MessageModel;
use YiluTech\YiMQ\Models\ProcessModel;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;

class YiMqActorClear
{
    public function run($context){

        try {
            \DB::connection()->getPdo()->exec("set innodb_lock_wait_timeout=6");
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
        $clearStatus = implode(',',[ProcessStatus::DONE,ProcessStatus::CANCELED]);
        $canClearProcesses = \DB::select("select * from $processTableName where id in (". $process_ids_string .") and status in (". $clearStatus .") for update skip locked");
        $canClearProcessIds = array_column($canClearProcesses,"id");
        //通过数组取差集得到在数据库不能删除的process,这里边包含 被锁定的、状态不对的，不存在的
        $failedClearProcessIds = array_diff($process_ids,$canClearProcessIds);

        //把$failedClearMessageIds 不带状态条件和for update 再查一次，排除已经被删除的message
        //(需要设置事务隔离为 Read Uncommitted，防止还未提交的message也被误判为已经删除)
        $failedClearProcessIds = ProcessModel::whereIn('id',$failedClearProcessIds)->get()->pluck('id')->toArray();

//        ProcessModel::whereIn('id',$canClearProcessIds)->delete();//用这个删除，单元测试中，有一个锁定message的测试，where in 条件大11的时候，就会失败，所以采用了存储过程一个个删除
        $ids = json_encode($canClearProcessIds);
        \DB::select("call yimq_clear('process','$ids')");
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
        //通过数组取差集得到在数据库不能删除的message,这里边包含 被锁定的、状态不对的，已经被删除的 和 正在创建中还未提交的
        $failedClearMessageIds = array_diff($message_ids,$canClearMessageIds);
        //把$failedClearMessageIds 不带状态条件和for update 再查询一次，排除已经已经被删除message被当做不能删除的message
        //(需要设置事务隔离为 Read Uncommitted，防止还未提交的message也被误判为已经删除)
        $failedClearMessageIds = MessageModel::whereIn('message_id',$failedClearMessageIds)->get()->pluck('message_id')->toArray();

//        SubtaskModel::whereIn('message_id',$canClearMessageIds)->delete();
//        MessageModel::whereIn('message_id',$canClearMessageIds)->delete();//用这个删除，单元测试中，有一个锁定message的测试，where in 条件大于6的时候，就会失败，所以采用了存储过程一个个删除


        $ids = json_encode($canClearMessageIds);
        \DB::select("call yimq_clear('message','$ids')");
        return $failedClearMessageIds;

    }

}