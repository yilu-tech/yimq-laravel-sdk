<?php

namespace Tests\Feature;

use Tests\TestCase;
use YiluTech\YiMQ\Constants\MessageServerStatus;
use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\ProcessStatus;
use YiluTech\YiMQ\Models\Message as MessageModel;
class SubtaskProcessorChildTransactionTest extends TestCase
{



    public function testTransactionXaTryCommit()
    {
        \YiMQ::mock()->transaction('transaction.xa.processor')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $id = $this->getProcessId();
        $processor = 'user.create.xa.child-transaction';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'XA',
            'producer' => 'user',
            'processor' => $processor,
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'username'=>"test$id"
            ]
        ];

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);


        \DB::reconnect();//需要重新连接数据里否则xa的prepare状态下无法进行其他数据库操作
        //已经是PREPARED状态，但还未commit 查出状态
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::PREPARING]);
        $parent_subtask = "user@$id";
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::PENDING]);

        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'XA',
            'id' => $id,
            'processor' => $processor,
        ];
        //第1次confirm

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['message'],'succeed');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::DONE]);
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::DONE]);
    }


    public function testTransactionXaTryRollback()
    {
        \YiMQ::mock()->transaction('transaction.xa.processor')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $id = $this->getProcessId();
        $processor = 'user.create.xa.child-transaction';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'XA',
            'producer' => 'user',
            'processor' => $processor,
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'username'=>"test$id"
            ]
        ];

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);


        \DB::reconnect();//需要重新连接数据里否则xa的prepare状态下无法进行其他数据库操作
        //已经是PREPARED状态，但还未commit 查出状态
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::PREPARING]);
        $parent_subtask = "user@$id";
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::PENDING]);

        $data['action'] = 'CANCEL';
        $data['context'] = [
            'type' => 'XA',
            'id' => $id,
            'processor' => $processor,
        ];
        //第1次confirm

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['message'],'canceled');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::CANCELED]);
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::CANCELED]);
    }



    public function testChildTransactionXaTryFailedAutoRollback()
    {
        \YiMQ::mock()->transaction('transaction.xa.processor')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->rollback()->reply(200);
        $id = $this->getProcessId();
        $processor = 'user.create.xa.child-transaction';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'XA',
            'producer' => 'user',
            'processor' => $processor,
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'username'=>"test$id",
                'failed' => true
            ]
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(400);
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>"user@$id",'status'=>MessageStatus::CANCELED]);
    }


    public function testEcAutoChildTransactionCommitSuccess()
    {
        $id = $this->getProcessId();
        \YiMQ::mock()->transaction('transaction.xa.processor-auto-child-transaction')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $userModel = $this->createMockUser();
        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'EC',
            'producer' => 'user',
            'processor' => 'user.update.ec.child-transaction',
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'id'=>$userModel->id,
                'username'=>"test$id"
            ]
        ];

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertDatabaseHas($this->userModelTable,['id'=>$userModel->id,'username'=>$data['context']['data']['username']]);
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::DONE]);
        $parent_subtask = "user@$id";
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::DONE]);

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'retry_succeed');
    }

    public function testEcManualCommitFailed()
    {
        $id = $this->getProcessId();
        \YiMQ::mock()->transaction('transaction.xa.processor-auto-child-transaction')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);

        $userModel1 = $this->createMockUser();
        $userModel2 = $this->createMockUser();
        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'EC',
            'producer' => 'user',
            'processor' => 'user.update.ec.child-transaction',
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'id'=>$userModel1->id,
                'username'=>$userModel2->username
            ]
        ];

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(500);
        $response->assertJson([
            'message'=>'user_exists'
        ]);
        $this->assertDatabaseMissing($this->userModelTable,['id'=>$userModel1->id,'username'=>$data['context']['data']['username']]);
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::DOING]);

        $parent_subtask = "user@$id";
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::CANCELED]);
    }

    public function testTccTryCommit()
    {
        \YiMQ::mock()->transaction('user.create.with-update')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);
        $id = $this->getProcessId();
        $processor = 'user.tcc_create-child-transaction';
        $username = "test$id";
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'TCC',
            'producer' => 'user',
            'processor' => $processor,
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'username'=>$username
            ]
        ];

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);

        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::PREPARED]);
        $this->assertDatabaseHas($this->userModelTable,['username'=>$username,'status'=>0]);

        $parent_subtask = "user@$id";
        $childMessage = MessageModel::where(['parent_subtask'=>$parent_subtask])->first();
        $this->assertEquals($childMessage->status,MessageStatus::PREPARED);

        //未confirm前检查子message的状态为prepared
        $childMessageCheckData['action'] = 'MESSAGE_CHECK';
        $childMessageCheckData['context'] = [
            'message_id' => $childMessage->message_id,
        ];
        $response = $this->json('POST','/yimq',$childMessageCheckData);
        $this->assertEquals($response->json('status'),MessageServerStatus::PREPARED);

        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'TCC',
            'id' => $id,
            'processor' => $processor
        ];
        //confirm
        $response = $this->json('POST','/yimq',$data);

        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'succeed');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::DONE]);
        $this->assertDatabaseHas($this->userModelTable,['username'=>$username,'status'=>1]);
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::DONE]);

        $response = $this->json('POST','/yimq',$childMessageCheckData);
        $response->assertStatus(200);
        $this->assertEquals($response->json('status'),MessageServerStatus::DONE);
    }




    public function testTccTryFailedAutoRollback()
    {
        \YiMQ::mock()->transaction('user.create.with-update')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->commit()->reply(200);
        $id = $this->getProcessId();
        $processor = 'user.tcc_create-child-transaction';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'TCC',
            'producer' => 'user',
            'processor' => $processor,
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'username'=>"test$id",
                'failed' => true
            ]
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(400);

        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::PREPARING]);

        $parent_subtask = "user@$id";
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::CANCELED]);
    }

    public function testTccTryAfterRollback()
    {
        \YiMQ::mock()->transaction('user.create.with-update')->reply(200);
        \YiMQ::mock()->prepare()->reply(200);
        \YiMQ::mock()->rollback()->reply(200);
        $id = $this->getProcessId();
        $processor = 'user.tcc_create-child-transaction';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'TCC',
            'producer' => 'user',
            'processor' => $processor,
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'username'=>"test$id"
            ]
        ];

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);

        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::PREPARED]);

        $parent_subtask = "user@$id";
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::PREPARED]);



        $data['action'] = 'CANCEL';
        $data['context'] = [
            'type' => 'TCC',
            'id' => $id,
            'processor' => $processor
        ];
        //第1次Cancel
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'succeed');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::CANCELED]);
        $this->assertDatabaseHas($this->messageTable,['parent_subtask'=>$parent_subtask,'status'=>MessageStatus::CANCELED]);
    }




}
