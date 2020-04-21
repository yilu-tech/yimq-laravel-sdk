<?php


namespace Tests\Services;


use Tests\App\Models\UserModel;
use YiluTech\YiMQ\Processor\EcProcessor;
use YiluTech\YiMQ\Processor\XaProcessor;

class UserUpdateEcManual extends EcProcessor
{
    public $manual_mode = true;


    protected function do()
    {
        \YiMQ::transaction('user.update.child',function (){
            $this->_ec_begin();
            $userModel = UserModel::find($this->data['id']);
            $userModel->username = $this->data['username'];
            $userModel->save();
            $this->_ec_done();
        })->begin();

    }

    protected function validate($validator)
    {
        $validator([]);
    }
}