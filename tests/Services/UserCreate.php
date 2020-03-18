<?php


namespace Tests\Services;


use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\App\Models\UserModel;
use YiluTech\YiMQ\Processor\XaProcessor;

class UserCreate extends XaProcessor
{

    function prepare()
    {
        $userModel = new UserModel();
        $userModel->username = $this->data['username'];
        $userModel->save();
        if(isset($this->data['failed'])){
            abort(400,'mock failed');
        }
    }
}