<?php

namespace Tests\Unit;

use Tests\App\Models\UserModel;
use Tests\TestCase;
use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Exceptions\YiMqHttpRequestException;
use YiluTech\YiMQ\Exceptions\YiMqSubtaskPrepareException;


class TransactionMessageTest extends TestCase
{

    public function testMessageCommit(){
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::transaction('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id]);
        \YiMQ::commit();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id,'status'=>MessageStatus::DONE]);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"DONE");
    }

    public function testMessageCommitLocalSuccessRemoteFailed(){
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(400);

        $message = \YiMQ::transaction('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id]);
        try{
            \YiMQ::commit();
        }catch(\Exception $exeption){
            $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id,'status'=>MessageStatus::DONE]);
        }

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"DONE");
    }

    public function testMessagePrepareFailedRollback(){
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(400);
        \YiMQ::mock()->rollback()->reply(200);

        $message = \YiMQ::transaction('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id]);
        $ecSubtask1 = \YiMQ::ec('content@content.change')->data(['title'=>'new title1'])->join();
        try{
            \YiMQ::commit();
        }catch(\Exception $exeption){
            $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id,'status'=>MessageStatus::PENDING]);
            \YiMQ::rollback();
        }
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id,'status'=>MessageStatus::CANCELED]);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"CANCELED");
    }

    public function testMessageRollback(){
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->rollback()->reply(200);

        $message = \YiMQ::transaction('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id]);
        \YiMQ::rollback();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id,'status'=>MessageStatus::CANCELED]);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"CANCELED");


    }

    public function testMessageRollbackLocalSuccessRemoteFailed(){
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->rollback()->reply(400);

        $message = \YiMQ::transaction('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id]);
        try {
            \YiMQ::rollback();
        }catch (\Exception $exception){
            $this->assertDatabaseHas($this->messageTable,['message_id'=>$message->id,'status'=>MessageStatus::CANCELED]);
        }

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"CANCELED");
    }

    public function testTrasactionTwiceError()
    {
        $message = \YiMQ::mock()->transaction('user.create')->reply(200);
        $message = \YiMQ::transaction('user.create')->begin();
        \YiMQ::mock()->rollback()->reply(200);
        $errorMessage = null;
        try{
            $message = \YiMQ::transaction('user.create')->begin();
        }catch (\Exception $exception){
            $errorMessage = $exception->getMessage();
            \YiMQ::rollback();
        }
        $this->assertEquals('MicroApi transaction message already exists.',$errorMessage);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"CANCELED");
    }

    public function testAddTccSubtask()
    {
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->tcc('user@user.create')->reply(200,['username'=>'jack']);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::transaction('user.create')->begin();
        $tccSubtask = \YiMQ::tcc('user@user.create')->data([])->try();
        $this->assertDatabaseHas($this->subtaskTable,['message_id'=>$tccSubtask->id]);
        \YiMQ::commit();

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"DONE");

    }

    public function testAddXaSubtask()
    {
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->xa('user@user.create')->reply(200,['username'=>'jack']);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::transaction('user.create')->begin();
        $tccSubtask = \YiMQ::xa('user@user.create')->data([])->prepare();
        $this->assertDatabaseHas($this->subtaskTable,['message_id'=>$tccSubtask->id]);
        \YiMQ::commit();

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"DONE");

    }
    public function testAddEcSubtaskAndPrepare()
    {
        \YiMQ::mock()->transaction('content.update')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::transaction('content.update')->begin();
        $ecSubtask1 = \YiMQ::ec('content@content.change')->data(['title'=>'new title1'])->join();
        $ecSubtask2 = \YiMQ::ec('content@content.change')->data(['title'=>'new title2'])->join();
        \YiMQ::commit();
        $this->assertDatabaseHas($this->subtaskTable,['subtask_id'=>$ecSubtask1->id]);
        $this->assertDatabaseHas($this->subtaskTable,['subtask_id'=>$ecSubtask2->id]);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"DONE");
    }

    public function testAddBcstSubtaskAndPrepare()
    {
        \YiMQ::mock()->transaction('content.update')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::transaction('content.update')->begin();
        $bcstSubtask = \YiMQ::bcst('content.change')->data(['title'=>'new title1'])->join();
        \YiMQ::commit();
        $this->assertDatabaseHas($this->subtaskTable,['subtask_id'=>$bcstSubtask->id]);

    }

    public function testRemoteConfimErrorIgnoreExceptions(){
        \YiMQ::mock()->transaction('transaction.test')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(500);
        $exception = null;
        try {
            \YiMQ::transaction('transaction.test')->begin();
            \YiMQ::commit();
        }catch (\Exception $e){
            $exception = $e;
            \YiMQ::rollback();
        }
        $this->assertNull($exception);
    }

    public function testRemoteRollbackErrorIgnoreExceptions(){
        \YiMQ::mock()->transaction('transaction.test')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);
        \YiMQ::mock()->rollback()->reply(500);
        $exception = null;
        try {
            try {
                \YiMQ::transaction('transaction.test')->begin();
                throw new \Exception('mock exception');
            }catch (\Exception $e){
                \YiMQ::rollback();
            }

        }catch (\Exception $e){
            $exception = $e;
        }
        $this->assertNull($exception);
    }


    public function testClosureTransaction(){
        $username = "name-".$this->getUserId();
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $userModel = \YiMQ::transaction('user.create',function () use($username){

            $ecSubtask1 = \YiMQ::ec('content@user.change')->data(['title'=>'new title1'])->join();
            $userModel = new UserModel();
            $userModel->username = $username;
            $userModel->save();
            return $userModel;
        })->begin();

        $this->assertDatabaseHas($this->userModelTable,['username'=>$username]);
    }

    public function testClosureTransactionRollback(){
        $username = "name-".$this->getUserId();
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);
        $exception = null;
        try{
            $userModel = \YiMQ::transaction('user.create',function () use($username){

                $ecSubtask1 = \YiMQ::ec('content@user.change')->data(['title'=>'new title1'])->join();
                $userModel = new UserModel();
                $userModel->username = $username;
                $userModel->save();
                throw new \Exception('test');
                return $userModel;
            })->begin();
        }catch (\Exception $e){
            $exception = $e;
        }
        $this->assertNotNull($exception);
        $this->assertDatabaseMissing($this->userModelTable,['username'=>$username]);
    }



}
