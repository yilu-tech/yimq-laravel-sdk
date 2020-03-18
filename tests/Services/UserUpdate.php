<?php


namespace Tests\Services;


use Tests\App\Models\UserModel;
use YiluTech\YiMQ\Processor\EcProcessor;
use YiluTech\YiMQ\Processor\XaProcessor;

class UserUpdate extends EcProcessor
{


    protected function do()
    {
        $userModel = UserModel::find($this->data['id']);
        $userModel->username = $this->data['username'];
        $userModel->save();
    }
}