<?php


namespace YiluTech\YiMQ\Subtask;

use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;
use YiluTech\YiMQ\Subtask\BaseSubtask\ProcessorSubtask;

class EcSubtask extends ProcessorSubtask
{
    public $serverType = "EC";
    public $type = SubtaskType::EC;

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
            'processor'=>$this->processor,
            'data'=> $this->getData()
        ];
    }
}