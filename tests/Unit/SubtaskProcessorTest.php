<?php

namespace Tests\Feature;

use Tests\TestCase;
use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\ProcessStatus;
class SubtaskProcessorTest extends TestCase
{

    public function testXaTryCommit()
    {
        $id = $this->getProcessId();
        $processor = 'user.xa.create';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'XA',
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

        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'XA',
            'id' => $id,
            'processor' => $processor,
        ];
        //第1次confirm
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['message'],'succeed');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::DONE]);

        //第2次confirm
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['message'],'retry_succeed');

        //尝试cancel后confirm
        $data['action'] = 'CANCEL';
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(500);
        $this->assertEquals($response->json()['message'],'Status is DONE.');
    }


    public function testXaTryFailedAutoRollback()
    {
        $id = $this->getProcessId();
        $processor = 'user.xa.create';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'XA',
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

        $data['action'] = 'CANCEL';
        $data['context'] = [
            'type' => 'XA',
            'id' => $id,
            'processor' => $processor
        ];
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['message'],'compensate_canceled');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::CANCELED]);
    }

    public function testXaTryAfterRollback()
    {
        $id = $this->getProcessId();
        $processor = 'user.xa.create';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'XA',
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



        $data['action'] = 'CANCEL';
        $data['context'] = [
            'type' => 'XA',
            'id' => $id,
            'processor' => $processor
        ];
        //第1次Cancel
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['message'],'canceled');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::CANCELED]);

        //第2次Cancel
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['message'],'retry_canceled');

        //cancel后尝试confirm
        $data['action'] = 'CONFIRM';
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(500);
        $this->assertEquals($response->json()['message'],'Status is CANCELED.');

    }
     public function testTccCancelNotExists(){
         $processor = 'user.tcc_create';
         $data['action'] = 'CANCEL';
         $data['context'] = [
             'type' => 'TCC',
             'id' => 112456317623,
             'processor' => $processor
         ];
         //第1次Cancel
         $response = $this->post('/yimq',$data);
         $this->assertEquals($response->json()['message'],'not_prepare');

     }
    public function testXaCancelNotExists(){
        $processor = 'user.xa.create';
        $data['action'] = 'CANCEL';
        $data['context'] = [
            'type' => 'XA',
            'id' => 1123123,
            'processor' => $processor
        ];
        //第1次Cancel
        $response = $this->post('/yimq',$data);
        $this->assertEquals($response->json()['message'],'not_prepare');
    }


    public function testTccTryCommit()
    {
        $id = $this->getProcessId();
        $processor = 'user.tcc_create';
        $username = "test$id";
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'TCC',
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

        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'TCC',
            'id' => $id,
            'processor' => $processor
        ];
        //第1次confirm
        $response = $this->json('POST','/yimq',$data);

        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'succeed');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::DONE]);
        $this->assertDatabaseHas($this->userModelTable,['username'=>$username,'status'=>1]);

        //第2次confirm
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'retry_succeed');

        //尝试cancel后confirm
        $data['action'] = 'CANCEL';
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(500);
        $this->assertEquals($response->json()['message'],'Status is DONE.');
    }


    public function testTccTryFailedAutoRollback()
    {
        $id = $this->getProcessId();
        $processor = 'user.tcc_create';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'TCC',
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

        $data['action'] = 'CANCEL';
        $data['context'] = [
            'type' => 'TCC',
            'id' => $id,
            'processor' => $processor
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['message'],'compensate_canceled');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::CANCELED]);
    }

    public function testTccTryAfterRollback()
    {
        $id = $this->getProcessId();
        $processor = 'user.tcc_create';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'TCC',
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



        $data['action'] = 'CANCEL';
        $data['context'] = [
            'type' => 'TCC',
            'id' => $id,
            'processor' => $processor
        ];
        //第1次Cancel
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'succeed');
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::CANCELED]);

        //第2次Cancel
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'retry_succeed');

        //cancel后尝试confirm
        $data['action'] = 'CONFIRM';
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(400);
        $this->assertEquals($response->json()['message'],'Status is CANCELED.');
    }

    public function testEcCommitSuccess()
    {
        $id = $this->getProcessId();

        $userModel = $this->createMockUser();
        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'EC',
            'processor' => 'user.ec.update',
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

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'retry_succeed');
    }

    public function testEcCommitFailed()
    {
        $id = $this->getProcessId();

        $userModel1 = $this->createMockUser();
        $userModel2 = $this->createMockUser();
        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'EC',
            'processor' => 'user.ec.update',
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'id'=>$userModel1->id,
                'username'=>$userModel2->username
            ]
        ];

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(500);
        $this->assertDatabaseMissing($this->userModelTable,['id'=>$userModel1->id,'username'=>$data['context']['data']['username']]);
        $this->assertDatabaseHas($this->processModelTable,['id'=>$id,'status'=>ProcessStatus::DOING]);
    }


    public function testBcstCommitSuccess()
    {
        $id = $this->getProcessId();
        $userModel = $this->createMockUser();
        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'LSTR',
            'processor' => \Tests\Services\UserUpdateListenerProcessor::class,
            'topic'=>'user@user.ec.update',
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

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'retry_succeed');
    }

}
