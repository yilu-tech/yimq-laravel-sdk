<?php


namespace Tests\Services;


use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\App\Models\UserModel;
use YiluTech\YiMQ\Message\TransactionMessage;
use YiluTech\YiMQ\Processor\XaProcessor;

class UserCreateXaChildTransactionProcessor extends XaProcessor
{
    public $childTransactionTopic = 'transaction.xa.processor';

    /**
     * 不需要特殊定义的时候，不需要覆盖这个方法
     */
    function childTransaction(TransactionMessage $transaction)
    {
       $transaction->delay(1000);
    }

    function prepare()
    {
        $userModel = new UserModel();
        $userModel->username = $this->data['username'];
        $userModel->save();

        $ecSubtask1 = \YiMQ::ec('user@user.ec.update')->data([
            'id'=> $userModel->id,
            'username'=>$userModel->username .'.update'
        ])->join();
        if(isset($this->data['failed'])){
            abort(400,'mock failed');
        }
        return $userModel->toArray();
    }


    protected function validate($validator)
    {
        $validator([]);
        // TODO: Implement validate() method.
    }
}