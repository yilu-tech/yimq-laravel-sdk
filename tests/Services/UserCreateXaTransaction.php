<?php


namespace Tests\Services;


use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\App\Models\UserModel;
use YiluTech\YiMQ\Processor\XaProcessor;

class UserCreateXaTransaction extends XaProcessor
{

    function beforeTransaction()
    {
        \YiMQ::transaction('transaction.xa.processor')->create();//创建远程事务
    }


    function prepare()
    {
        \YiMQ::transaction()->start(); //开始事务
        $ecSubtask1 = \YiMQ::ec('content@content.change')->data(['title'=>'new title1'])->join();
        $userModel = new UserModel();
        $userModel->username = $this->data['username'];
        $userModel->save();
        if(isset($this->data['failed'])){
            abort(400,'mock failed');
        }
        \YiMQ::transaction()->localCommmit(); //事务本地commit
        return $userModel->toArray();
    }

    function afterTransaction()
    {
        \YiMQ::transaction()->remoteCommit(); //事务远程commit
    }

    function catchTransaction()
    {
        \YiMQ::transaction()->remoteRollback();//事务远程回滚
    }

    protected function validate($validator)
    {
        $validator([]);
        // TODO: Implement validate() method.
    }
}