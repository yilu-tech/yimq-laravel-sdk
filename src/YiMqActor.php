<?php


namespace YiluTech\YiMQ;



use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\SubtaskServerType;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Exceptions\YiMqSystemException;
use YiluTech\YiMQ\Models\Message as  MessageModel;
use YiluTech\YiMQ\Models\ProcessModel;

class YiMqActor
{
    private $processorsMap;
    private $listenersMap;
    private $app;
    public function __construct(\App $app)
    {
        $this->app = $app;
        $this->processorsMap = config('yimq.processors');
        $this->listenersMap = config('yimq.broadcast_listeners');

    }

    public function try($context){
        \Log::debug('try',$context);
        $processor = $this->getProcessor($context);
        return $processor->runTry($context);

    }

    public function confirm($context){
        \Log::debug('confirm',$context);
        $processor = $this->getProcessor($context);
        return $processor->runConfirm($context);
    }

    public function cancel($context){
        $processor = $this->getProcessor($context);
        return $processor->runCancel($context);
    }

    public function messageCheck($context){
        \Log::debug($context);
        $messageModel = MessageModel::lockForUpdate()->find($context['message_id']);
        if(!isset($messageModel)){
            throw new YiMqSystemException('Message not exists.');
        }
        if($messageModel->status == MessageStatus::DONE){
            return ['status'=>'DONE'];
        }
        if($messageModel->status == MessageStatus::CANCELED){
            return ['status'=>'CANCELED'];
        }
        //如果lockForUpdate能拿到message且处于PENDING状态，说明本地回滚后设置 message状态失败，check的时候补偿状态
        if($messageModel->status == MessageStatus::PENDING){
            $messageModel->status = MessageStatus::CANCELED;
            $messageModel->save();
            return ['status'=>'CANCELED'];
        }
    }


    private function getProcessor($context){


        if($context['type'] == SubtaskServerType::LSTR){
            $processor = $context['processor'];
            if(!isset($this->listenersMap[$processor])){
                throw new YiMqSystemException("Processor <$processor> not exists");
            }
            if($context['topic'] != $this->listenersMap[$processor]){
                $contextTopic = $context['topic'];
                $localTopic = $this->listenersMap[$processor];
                throw new YiMqSystemException("Processor '$processor' => '$localTopic' can not process '$contextTopic'.");
            }
            return resolve($processor);
        }else if(in_array($context['type'],[SubtaskServerType::XA,SubtaskServerType::TCC,SubtaskServerType::EC])) {
            $processor = $context['processor'];

            if(!isset($this->processorsMap[$processor])){
                throw new YiMqSystemException("Processor <$processor> not exists");
            }
            return resolve($this->processorsMap[$processor]);
        }else{
            throw new YiMqSystemException("Subtask type ". $context['type'] . 'not suport.');
        }


    }
}