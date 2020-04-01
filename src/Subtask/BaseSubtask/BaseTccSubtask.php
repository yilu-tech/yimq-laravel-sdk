<?php


namespace YiluTech\YiMQ\Subtask\BaseSubtask;


use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;

class BaseTccSubtask extends ProcessorSubtask
{
    public $prepareResult;

    public function _try()
    {

        if($this->mockManager->hasMocker($this)){//TODO 增加一个test环境生效的判断
            $result = $this->mockManager->runMocker($this);
        }else{
            $result = $this->client->callServer('subtask',$this->getContext());
        }
        $this->id = $result['id'];
        $this->prepareResult = $result['prepareResult'];


        $this->model = new SubtaskModel();
        $this->model->id = $this->id;
        $this->model->message_id = $this->message->id;
        $this->model->status = SubtaskStatus::PREPARED;
        $this->model->type = $this->type;
        $this->model->save();

        $this->message->addTccSubtask($this);
        return $this;
    }

    public function getContext()
    {
        return [
            'message_id' => $this->message->id,
            'type' => $this->serverType,
            'processor' => $this->processor,
            'data' => $this->data
        ];
    }

}