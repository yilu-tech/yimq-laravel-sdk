<?php

namespace Tests\Unit;

use Tests\TestCase;
use YiluTech\YiMQ\Constants\MessageStatus;


class TransactionMessageTest extends TestCase
{

    public function testMessageCommit(){
        \YiMQ::mock()->topic('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::topic('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id]);
        \YiMQ::commit();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::DONE]);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"DONE");
    }

    public function testMessageCommitLocalSuccessRemoteFailed(){
        \YiMQ::mock()->topic('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(400);

        $message = \YiMQ::topic('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id]);
        try{
            \YiMQ::commit();
        }catch(\Exception $exeption){
            $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::DONE]);
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
        \YiMQ::mock()->topic('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(400);
        \YiMQ::mock()->rollback()->reply(200);

        $message = \YiMQ::topic('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id]);
        $ecSubtask1 = \YiMQ::ec('content@content.change')->data(['title'=>'new title1'])->run();
        try{
            \YiMQ::commit();
        }catch(\Exception $exeption){
            $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::PENDING]);
            \YiMQ::rollback();
        }
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::CANCELED]);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"CANCELED");
    }

    public function testMessageRollback(){
        \YiMQ::mock()->topic('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->rollback()->reply(200);

        $message = \YiMQ::topic('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id]);
        \YiMQ::rollback();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::CANCELED]);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'message_id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"CANCELED");


    }

    public function testMessageRollbackLocalSuccessRemoteFailed(){
        \YiMQ::mock()->topic('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->rollback()->reply(400);

        $message = \YiMQ::topic('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id]);
        try {
            \YiMQ::rollback();
        }catch (\Exception $exception){
            $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::CANCELED]);
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
        $message = \YiMQ::mock()->topic('user.create')->reply(200);
        $message = \YiMQ::topic('user.create')->begin();
        \YiMQ::mock()->rollback()->reply(200);
        $errorMessage = null;
        try{
            $message = \YiMQ::topic('user.create')->begin();
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
        \YiMQ::mock()->topic('user.create')->reply(200);
        \YiMQ::mock()->tcc('user@user.create')->reply(200,['username'=>'jack']);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::topic('user.create')->begin();
        $tccSubtask = \YiMQ::tcc('user@user.create')->data([])->run();
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$tccSubtask->id]);
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
        \YiMQ::mock()->topic('user.create')->reply(200);
        \YiMQ::mock()->xa('user@user.create')->reply(200,['username'=>'jack']);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::topic('user.create')->begin();
        $tccSubtask = \YiMQ::xa('user@user.create')->data([])->run();
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$tccSubtask->id]);
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
        \YiMQ::mock()->topic('content.update')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::topic('content.update')->begin();
        $ecSubtask1 = \YiMQ::ec('content@content.change')->data(['title'=>'new title1'])->run();
        $ecSubtask2 = \YiMQ::ec('content@content.change')->data(['title'=>'new title2'])->run();
        \YiMQ::commit();
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$ecSubtask1->id]);
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$ecSubtask2->id]);

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
        \YiMQ::mock()->topic('content.update')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $message = \YiMQ::topic('content.update')->begin();
        $bcstSubtask = \YiMQ::bcst('content.change')->data(['title'=>'new title1'])->run();
        \YiMQ::commit();
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$bcstSubtask->id]);

    }

    public function testRemoteConfimErrorIgnoreExceptions(){
        \YiMQ::mock()->topic('transaction.test')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(500);
        $exception = null;
        try {
            \YiMQ::topic('transaction.test')->begin();
            \YiMQ::commit();
        }catch (\Exception $e){
            $exception = $e;
            \YiMQ::rollback();
        }
        $this->assertNull($exception);
    }

    public function testRemoteRollbackErrorIgnoreExceptions(){
        \YiMQ::mock()->topic('transaction.test')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);
        \YiMQ::mock()->rollback()->reply(500);
        $exception = null;
        try {
            try {
                \YiMQ::topic('transaction.test')->begin();
                throw new \Exception('mock exception');
            }catch (\Exception $e){
                \YiMQ::rollback();
            }

        }catch (\Exception $e){
            $exception = $e;
        }
        $this->assertNull($exception);
    }



}
