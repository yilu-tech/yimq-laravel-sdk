<?php


namespace Tests\Services;


use Tests\App\Models\UserModel;
use YiluTech\YiMQ\Processor\EcProcessor;
use YiluTech\YiMQ\Processor\XaProcessor;

class UserUpdateEcChildTransactionProcessor extends EcProcessor
{
    public $childTransactionTopic = 'transaction.xa.processor-auto-child-transaction';


    protected function do()
    {
        $userModel = UserModel::find($this->data['id']);
        if(UserModel::where('username',$this->data['username'])->exists()){
            throw new \Exception('user_exists');
        }
        $userModel->username = $this->data['username'];
        $userModel->save();
    }

    protected function validate($validator)
    {
        $validator([]);
    }
}