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
        \YiMQ::prepare();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::PENDING]);
        \YiMQ::commit();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::DONE]);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'id' => $message->id,
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
        \YiMQ::prepare();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::PENDING]);
        try{
            \YiMQ::commit();
        }catch(\Exception $exeption){
            $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::DONE]);
        }

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"DONE");
    }

    public function testMessagePrepareFailedRollback(){
        \YiMQ::mock()->topic('user.create')->reply(200);
        \YiMQ::mock()->prepare()->reply(400);

        $message = \YiMQ::topic('user.create')->begin();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id]);

        try{
            \YiMQ::prepare();
        }catch(\Exception $exeption){
            $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::PENDING]);
            \YiMQ::rollback();
            $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::CANCELED]);
        }

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'id' => $message->id,
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
        \YiMQ::prepare();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::PENDING]);
        \YiMQ::rollback();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::CANCELED]);

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'id' => $message->id,
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
        \YiMQ::prepare();
        $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::PENDING]);
        try {
            \YiMQ::rollback();
        }catch (\Exception $exception){
            $this->assertDatabaseHas($this->messageTable,['id'=>$message->id,'status'=>MessageStatus::CANCELED]);
        }

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"CANCELED");
    }

    public function testTrasactionTwiceError()
    {
        $message = \YiMQ::mock()->topic('user.create')->reply(200);
        $message = \YiMQ::topic('user.create')->begin();
        try{
            $message = \YiMQ::topic('user.create')->begin();
        }catch (\Exception $exception){
            $this->assertEquals('MicroApi transaction message already exists.',$exception->getMessage());
        }

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'id' => $message->id,
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
        \YiMQ::prepare();
        \YiMQ::commit();

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'id' => $message->id,
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
        \YiMQ::prepare();
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$ecSubtask1->id]);
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$ecSubtask2->id]);
        \YiMQ::commit();

        $data['action'] = 'MESSAGE_CHECK';
        $data['context'] = [
            'id' => $message->id,
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),"DONE");
    }
}
