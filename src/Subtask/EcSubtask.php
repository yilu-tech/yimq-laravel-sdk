<?php


namespace YiluTech\YiMQ\Subtask;

use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;

class EcSubtask extends Subtask
{
    public $serverType = "EC";
    public $type = SubtaskType::EC;

    public function run()
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
}