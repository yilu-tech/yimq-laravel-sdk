<?php


namespace YiluTech\YiMQ\Subtask;

use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Message\TransactionMessage;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;
use YiluTech\YiMQ\Subtask\BaseSubtask\Subtask;
use YiluTech\YiMQ\YiMqClient;

class BcstSubtask extends Subtask
{
    public $serverType = "BCST";
    public $type = SubtaskType::BCST;
    public $topic;

    public function __construct(YiMqClient $client, TransactionMessage $message,$topic)
    {
        parent::__construct($client, $message);
        $this->topic = $topic;
    }

    public function join()
    {
        $this->message->addEcSubtask($this);
        return $this;
    }

    public function save(){
        $this->model = new SubtaskModel();
        $this->model->id = $this->id;
        $this->model->message_id = $this->message->id;
        $this->model->status = SubtaskStatus::PREPARED;
        $this->model->type = $this->type;
        $this->model->save();
    }
    public function getContext(){
        return [
            'type'=> $this->serverType,
            'topic'=>$this->topic,
            'data'=> $this->getData()
        ];
    }


}