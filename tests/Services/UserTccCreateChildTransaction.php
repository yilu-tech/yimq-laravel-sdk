<?php


namespace Tests\Services;


use Nexmo\User\User;
use Tests\App\Models\UserModel;
use YiluTech\YiMQ\Processor\EcProcessor;
use YiluTech\YiMQ\Processor\TccProcessor;
use YiluTech\YiMQ\Processor\XaProcessor;

class UserTccCreateChildTransaction extends TccProcessor
{
    public $childTransactionTopic = 'user.create.with-update';
    public $status=[
        'pending' => 0,
        'active' =>1
    ];

    public function try()
    {
        $userModel = new UserModel();
        $userModel->username = $this->data['username'];
        $userModel->status = $this->status['pending'];
        $userModel->save();
        if(isset($this->data['failed'])){
            abort(400,'mock failed');
        }

        if(isset($this->data['timeout'])){
            usleep($this->data['timeout']*1000 *2);
        }

        $ecSubtask1 = \YiMQ::ec('user@user.ec.update')->data([
            'id'=> $userModel->id,
            'username'=>$userModel->username .'.update'
        ])->join();

        return $userModel->toArray();
    }

    public function confirm()
    {
        $id = $this->processModel->try_result['id'];
        $userModel = UserModel::find($id);
        $userModel->status = $this->status['active'];
        $userModel->save();
    }

    public function cancel()
    {
        $id = $this->processModel->try_result['id'];
        $userModel = UserModel::find($id);
        $userModel->delete();
    }

    protected function validate($validator)
    {
        $validator([]);
    }
}