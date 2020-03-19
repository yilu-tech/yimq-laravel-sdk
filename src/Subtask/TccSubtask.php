<?php


namespace YiluTech\YiMQ\Subtask;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;

class TccSubtask extends Subtask
{
    public $prepareResult;

    public function run()
    {

        $context = [
            'message_id' => $this->message->id,
            'type' => 'TCC',
            'processor' => $this->processor,
            'data' => $this->data
        ];
        if($this->mockManager->hasMocker($this)){//TODO 增加一个test环境生效的判断
            $result = $this->mockManager->runMocker($this);
        }else{
            $result = $this->client->callServer('subtask',$context);
        }
        $this->id = $result['id'];
        $this->prepareResult = $result['prepareResult'];


        $this->model = new SubtaskModel();
        $this->model->id = $this->id;
        $this->model->message_id = $this->message->id;
        $this->model->status = SubtaskStatus::PREPARED;
        $this->model->type = SubtaskType::TCC;
        $this->model->save();

        $this->message->addTccSubtask($this);
        return $this;
    }
}