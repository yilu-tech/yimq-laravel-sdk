<?php


namespace YiluTech\YiMQ;



use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Models\Message as  MessageModel;

class YiMqActor
{
    private $processorsMap;
    private $app;
    public function __construct(\App $app)
    {
        $this->app = $app;
        $this->processorsMap = config('yimq.processors');

    }

    public function try($context){
        $processor = $this->getProcessor($context['processor']);
        $processor->run($context);
        return ['status'=>'success'];

    }

    public function confirm($context){
        $processor = $this->getProcessor($context['processor']);
        return $processor->confirm($context);
    }

    public function cancel($context){
        $processor = $this->getProcessor($context['processor']);
        return $processor->cancel($context);
    }

    public function messageCheck($context){
        $messageModel = MessageModel::lockForUpdate()->find($context['id']);
        if(!isset($messageModel)){
            abort(400,'Message not exists.');
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



    private function getProcessor($processor){
        if(!isset($this->processorsMap[$processor])){
            throw new \Exception("Processor <$processor> not exists");
        }
        return resolve($this->processorsMap[$processor]);
    }
}