<?php


namespace Tests\Services;


use Tests\App\Models\UserModel;
use YiluTech\YiMQ\Processor\EcProcessor;
use YiluTech\YiMQ\Processor\LstrProcessor;
use YiluTech\YiMQ\Processor\XaProcessor;

class UserUpdateListener extends LstrProcessor
{


    public function getCondition(){
        return <<<EOT
        if(data.id > 1){
            return true;
        }
EOT;
    }

    function validate($validator)
    {
        $validator([
            'id' => 'required',
        ]);
    }

    protected function do()
    {
        $userModel = UserModel::find($this->data['id']);
        $userModel->username = $this->data['username'];
        $userModel->save();
    }

}