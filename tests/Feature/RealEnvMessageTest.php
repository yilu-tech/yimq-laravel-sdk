<?php


namespace Tests\Feature;


use Tests\App\Models\UserModel;
use Tests\TestCase;
use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Exceptions\YiMqHttpRequestException;

class RealEnvMessageTest extends TestCase
{
    public function testMessageAddEcAfterCommit(){

        $userModel = $this->createMockUser();

        $ecData['id'] = $userModel->id;
        $ecData['username'] = $userModel->username.'.change';

        $message = \YiMQ::transaction('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id]);


        $ecSubtask = \YiMQ::ec('user@user.update')->data($ecData)->join();
        \YiMQ::commit();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id,'status'=>MessageStatus::DONE]);
        sleep(1);
        $this->assertDatabaseHas($this->userModelTable,['username'=>$ecData['username'] ]);
    }

    public function testMessageAddEcAfterRollback(){

        $userModel = $this->createMockUser();

        $id = $this->getSubtaskId();

        $message = \YiMQ::transaction('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id]);

        $ecData['id'] = $userModel->id;
        $ecData['username'] = $userModel->username.'.change';
        $ecSubtask = \YiMQ::ec('user@user.update')->data($ecData)->join();

        \YiMQ::rollback();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id,'status'=>MessageStatus::CANCELED]);
        $this->assertDatabaseMissing($this->subtaskTable,['subtask_id'=>$ecSubtask->id]);
    }

    public function testAddXaSubtask()
    {
        $tccData['username'] = "name-".$this->getUserId();
        $message = \YiMQ::transaction('user.create')->delay(10*1000)->begin();


        $tccSubtask = \YiMQ::xa('user@user.create')->data($tccData)->prepare();
        $this->assertDatabaseHas($this->subtaskTable,['subtask_id'=>$tccSubtask->id]);
        //通过行锁确定是否在processor中产生数据
        try {
            \DB::getPdo()->exec("set innodb_lock_wait_timeout=1");
            UserModel::create(['username'=>$tccData['username']]);
        }catch (\Exception $e){
            $this->assertEquals($e->getCode(),'HY000');
        }

        \YiMQ::commit();
        //暂停等待协调器去confirm
        usleep(800*1000);
        $this->assertDatabaseHas($this->userModelTable,['id'=>$tccSubtask->prepareResult['id']]);
    }

    public function testAddXaSubtaskLocalCommitFailedTimeoutCheck()
    {
        $tccData['username'] = "name-".$this->getUserId();
        \YiMQ::mock()->commit()->reply(400);

        $message = \YiMQ::transaction('user.create')->delay(1*1000)->data([])->begin();
        $tccSubtask = \YiMQ::xa('user@user.create')->data($tccData)->prepare();
        $this->assertDatabaseHas($this->subtaskTable,['subtask_id'=>$tccSubtask->id]);
        $errorMsg = null;
        try {
            \YiMQ::commit();
        }catch (YiMqHttpRequestException $e){
            $errorMsg = $e->getMessage();
        }
        $this->assertNull($errorMsg);

        //暂停等待message超时确认message状态后去confirm
        sleep(3);
        $this->assertDatabaseHas($this->userModelTable,['id'=>$tccSubtask->prepareResult['id']]);
    }


    public function testAddXaSubtaskRollback()
    {
        $message = \YiMQ::transaction('user.create')->delay(10*1000)->begin();

        $tccData['username'] = "name-".$this->getMessageId();
        $tccSubtask = \YiMQ::xa('user@user.create')->data($tccData)->prepare();
        $this->assertDatabaseHas($this->subtaskTable,['subtask_id'=>$tccSubtask->id]);

        //通过行锁确定是否在processor中产生数据
        try {
            \DB::getPdo()->exec("set innodb_lock_wait_timeout=1");
            UserModel::create(['username'=>$tccData['username']]);
        }catch (\Exception $e){
            $this->assertEquals($e->getCode(),'HY000');
        }

        \YiMQ::rollback();
        //通过插入数据确定username的杭锁已经释放
        UserModel::create(['username'=>$tccData['username']]);
    }
    public function testAddXaSubtaskRemoteRollbackFaildTimeoutCheck()
    {
        \YiMQ::mock()->rollback()->reply(500);
        $message = \YiMQ::transaction('user.create')->delay(2*1000)->data([])->begin();

        $tccData['username'] = "name-".$this->getMessageId();
        $tccSubtask = \YiMQ::xa('user@user.create')->data($tccData)->prepare();
        $this->assertDatabaseHas($this->subtaskTable,['subtask_id'=>$tccSubtask->id]);

        $errorMsg = null;
        try {
            \YiMQ::rollback();
        }catch (\Exception $e){
            $errorMsg = $e->getMessage();
        }
        $this->assertNull($errorMsg);

        //通过插入数据确定username的行锁已经释放
        UserModel::create(['username'=>$tccData['username']]);
    }
    /**
     * 在事务外提前创建一个用户，
     * 然后通过tcc1创建一个用户，
     * 再通过tcc2创建和提前创建用户同名的用户触发try失败
     * 判断tcc1创建的用户是否不存在判断是否回滚
     */
    public function testAddXaSubtaskTryFailedAfterRollback()
    {
        \DB::getPdo()->exec("set innodb_lock_wait_timeout=1");

        $userModel = $this->createMockUser();
        $tccData2['username'] = $userModel->username;

        $tccData1['username'] = "name-".$this->getUserId();



        $message = \YiMQ::transaction('user.create')->delay(10*1000)->begin();
        $tccSubtask1 = \YiMQ::xa('user@user.create')->data($tccData1)->prepare();

        try{
            $tccSubtask2 = \YiMQ::xa('user@user.create')->data($tccData2)-> prepare();
        }catch (\Exception $e){
            \YiMQ::rollback();
        }
        $this->assertDatabaseMissing($this->userModelTable,['id'=>$tccSubtask1->prepareResult['id']]);
        UserModel::create(['username'=>$tccData1['username']]);
    }


    public function testAddXaSubtaskTryFailedRemoteCheckoutAfterRollback()
    {
        \DB::getPdo()->exec("set innodb_lock_wait_timeout=1");

        $userModel = $this->createMockUser();
        $tccData2['username'] = $userModel->username;

        $tccData1['username'] = "name-".$this->getUserId();



        $message = \YiMQ::transaction('user.create')->delay(1*1000)->begin();
        $tccSubtask1 = \YiMQ::xa('user@user.create')->data($tccData1)->prepare();

        try{
            $tccSubtask2 = \YiMQ::xa('user@user.create')->data($tccData2)->prepare();
        }catch (\Exception $e){
            \DB::reconnect();//释放锁
            sleep(3);
            \YiMQ::rollback();
        }
        $this->assertDatabaseMissing($this->userModelTable,['id'=>$tccSubtask1->prepareResult['id']]);
        UserModel::create(['username'=>$tccData1['username']]);
    }


}