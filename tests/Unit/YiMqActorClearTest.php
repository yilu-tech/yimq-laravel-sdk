<?php


namespace Tests\Unit;

use Tests\App\Models\UserModel;
use Tests\TestCase;
use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\Message as MessageModel;
use YiluTech\YiMQ\Services\YiMqActorClear;
use YiluTech\YiMQ\Models\ProcessModel;

class YiMqActorClearTest extends TestCase
{
    public function testClearSuccess(){
        $username = "name-".$this->getUserId();
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->tcc('content@user.update')->reply(200,[]);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $userModel = \YiMQ::transaction('user.create',function () use($username){
            $bcstSubtask = \YiMQ::bcst('content.change')->data(['title'=>'new title1'])->join();
            $ecSubtask = \YiMQ::ec('content@user.change')->data(['title'=>'new title1'])->join();
            $tccSubtask = \YiMQ::tcc('content@user.update')->data([])->try();
            $userModel = new UserModel();
            $userModel->username = $username;
            $userModel->save();
            return $userModel;
        })->begin();
        $this->assertDatabaseHas($this->userModelTable,['username'=>$username]);
        $this->createProcess(SubtaskStatus::DONE);
        $this->createProcess(SubtaskStatus::CANCELED);

        $yimqActorClear =  resolve(YiMqActorClear::class );
        $message_ids = MessageModel::all()->pluck('message_id');
        $process_ids = ProcessModel::all()->pluck('id');
        $context = [
            'message_ids' => $message_ids,
            'process_ids' => $process_ids
        ];
        $yimqActorClear->run($context);
    }

    public function createProcess($status){
        $processModel =  new ProcessModel();
        $processModel->id = $this->getProcessId();
        $processModel->message_id = $this->getMessageId();
        $processModel->type = SubtaskType::EC;
        $processModel->data = [];
        $processModel->processor="user.create";
        $processModel->status = $status;
        $processModel->save();
    }

}