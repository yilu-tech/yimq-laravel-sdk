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
        \DB::transaction(function ()use($context){
            $this->deleteMessages($context['message_ids']);
            $this->deleteProcesses($context['process_ids']);
        });
        return ["message"=>'success'];

    }

    private function deleteProcesses($process_ids){
        foreach ($process_ids as $process_id){
           $processModel = ProcessModel::lockForUpdate()->find($process_id);
           if($processModel){
               $this->deleteProcess($processModel);
           }
        }
    }

    private  function deleteProcess($processModel){
        if(in_array($processModel->status,[SubtaskStatus::DONE,SubtaskStatus::CANCELED])){
            $processModel->delete();
        }else{
            throw new YiMqSystemException('message ' . $processModel->id.'status is '.$processModel->status );
        }
    }

    private function deleteMessages($message_ids){
        foreach ($message_ids as $message_id){
            $message = MessageModel::where('message_id',$message_id)->lockForUpdate()->first();
            if($message){
                $this->deleteMessage($message);
            }
        }
    }
    private function deleteMessage($message){
        if(in_array($message->status,[MessageStatus::DONE,MessageStatus::CANCELED])){
            $subtasks = SubtaskModel::where('message_id',$message->message_id)->get();
            foreach ($subtasks as $subtask){
                $subtask->delete();
            }
            $message->delete();
        }else{
            throw new YiMqSystemException('message ' . $message->message_id.'status is '.$message->status );
        }
    }

}