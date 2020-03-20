<?php


namespace Tests\Feature;


use Tests\App\Models\UserModel;
use Tests\TestCase;
use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\SubtaskStatus;

class RealEnvMessageTest extends TestCase
{
    public function testMessageAddEcAfterCommit(){

        $userModel = $this->createMockUser();

        $ecData['id'] = $userModel->id;
        $ecData['username'] = $userModel->username.'.change';

        $message = \YiMQ::topic('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id]);


        $ecSubtask = \YiMQ::ec('user@user.update')->data($ecData)->run();
        \YiMQ::prepare();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::PENDING]);
        \YiMQ::commit();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::DONE]);
    }

    public function testMessageAddEcAfterRollback(){

        $userModel = $this->createMockUser();

        $id = $this->getSubtaskId();

        $message = \YiMQ::topic('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id]);

        $ecData['id'] = $userModel->id;
        $ecData['username'] = $userModel->username.'.change';
        $ecSubtask = \YiMQ::ec('user@user.update')->data($ecData)->run();
        \YiMQ::prepare();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::PENDING]);
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$ecSubtask->id,'status'=>SubtaskStatus::PREPARED]);
        \YiMQ::rollback();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::CANCELED]);
        $this->assertDatabaseMissing($this->subtaskTable,['id'=>$ecSubtask->id]);
    }

    public function testAddTccSubtask()
    {
        $tccData['username'] = "name-".$this->getUserId();
        $message = \YiMQ::topic('user.create')->delay(10*1000)->begin();


        $tccSubtask = \YiMQ::tcc('user@user.create')->data($tccData)->run();
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$tccSubtask->id]);
        //通过行锁确定是否在processor中产生数据
        try {
            \DB::getPdo()->exec("set innodb_lock_wait_timeout=1");
            UserModel::create(['username'=>$tccData['username']]);
        }catch (\Exception $e){
            $this->assertEquals($e->getCode(),'HY000');
        }
        \YiMQ::prepare();
        \YiMQ::commit();
        //暂停等待协调器去confirm
        usleep(800*1000);
        $this->assertDatabaseHas($this->userModelTable,['id'=>$tccSubtask->prepareResult['id']]);
    }

    public function testAddTccSubtaskLocalCommitFailedTimeoutCheck()
    {
        $tccData['username'] = "name-".$this->getUserId();
        $message = \YiMQ::topic('user.create')->delay(1*1000)->data(['_remoteCommitFailed'=>'true'])->begin();
        $tccSubtask = \YiMQ::tcc('user@user.create')->data($tccData)->run();
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$tccSubtask->id]);
        \YiMQ::prepare();
        \YiMQ::commit();
        //暂停等待message超时确认message状态后去confirm
        sleep(3);
        $this->assertDatabaseHas($this->userModelTable,['id'=>$tccSubtask->prepareResult['id']]);
    }


    public function testAddTccSubtaskRollback()
    {
        $message = \YiMQ::topic('user.create')->delay(10*1000)->begin();

        $tccData['username'] = "name-".$this->getMessageId();
        $tccSubtask = \YiMQ::tcc('user@user.create')->data($tccData)->run();
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$tccSubtask->id]);

        //通过行锁确定是否在processor中产生数据
        try {
            \DB::getPdo()->exec("set innodb_lock_wait_timeout=1");
            UserModel::create(['username'=>$tccData['username']]);
        }catch (\Exception $e){
            $this->assertEquals($e->getCode(),'HY000');
        }

        \YiMQ::prepare();
        \YiMQ::rollback();
        //通过插入数据确定username的杭锁已经释放
        UserModel::create(['username'=>$tccData['username']]);
    }
    public function testAddTccSubtaskRemoteRollbackFaildTimeoutCheck()
    {
        $message = \YiMQ::topic('user.create')->delay(2*1000)->data(['_remoteCancelFailed'=>'true'])->begin();

        $tccData['username'] = "name-".$this->getMessageId();
        $tccSubtask = \YiMQ::tcc('user@user.create')->data($tccData)->run();
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$tccSubtask->id]);

        \YiMQ::prepare();
        \YiMQ::rollback();
        //通过插入数据确定username的杭锁已经释放
        UserModel::create(['username'=>$tccData['username']]);
    }
    /**
     * 在事务外提前创建一个用户，
     * 然后通过tcc1创建一个用户，
     * 再通过tcc2创建和提前创建用户同名的用户触发try失败
     * 判断tcc1创建的用户是否不存在判断是否回滚
     */
    public function testAddTccSubtaskTryFailedAfterRollback()
    {
        \DB::getPdo()->exec("set innodb_lock_wait_timeout=1");

        $userModel = $this->createMockUser();
        $tccData2['username'] = $userModel->username;

        $tccData1['username'] = "name-".$this->getUserId();



        $message = \YiMQ::topic('user.create')->begin();
        $tccSubtask1 = \YiMQ::tcc('user@user.create')->data($tccData1)->run();

        try{
            $tccSubtask2 = \YiMQ::tcc('user@user.create')->data($tccData2)->run();
        }catch (\Exception $e){
            \YiMQ::rollback();
            $this->assertDatabaseMissing($this->userModelTable,['id'=>$tccSubtask1->prepareResult['id']]);
            UserModel::create(['username'=>$tccData1['username']]);
        }
    }



}