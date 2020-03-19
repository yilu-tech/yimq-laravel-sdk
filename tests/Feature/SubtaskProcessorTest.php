<?php

namespace Tests\Feature;

use Tests\App\Models\UserModel;
use Tests\TestCase;
use YiluTech\YiMQ\Constants\SubtaskStatus;
class SubtaskProcessorTest extends TestCase
{

    /**
     * A basic test example.
     *
     * @return void
     */

    public function testTccTryCommit()
    {
        $id = $this->getSubtaskId();
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'TCC',
            'processor' => 'user@user.create',
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
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$id,'status'=>SubtaskStatus::PREPARING]);

        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'id' => $id,
            'processor' => 'user@user.create'
        ];
        //第1次confirm
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'succeed');
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$id,'status'=>SubtaskStatus::DONE]);

        //第2次confirm
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'retry_succeed');

        //尝试cancel后confirm
        $data['action'] = 'CANCEL';
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(400);
        $this->assertEquals($response->json()['message'],'Status is not PREPARING or CANCELED.');
    }

    public function testTccTryFailedAutoRollback()
    {
        $id = $this->getSubtaskId();
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'TCC',
            'processor' => 'user@user.create',
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
            'id' => $id,
            'processor' => 'user@user.create'
        ];
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'retry_succeed');
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$id,'status'=>SubtaskStatus::CANCELED]);
    }


    public function testTccTryAfterRollback()
    {
        $id = $this->getSubtaskId();
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'TCC',
            'processor' => 'user@user.create',
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
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$id,'status'=>SubtaskStatus::PREPARING]);



        $data['action'] = 'CANCEL';
        $data['context'] = [
            'id' => $id,
            'processor' => 'user@user.create'
        ];
        //第1次Cancel
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'succeed');
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$id,'status'=>SubtaskStatus::CANCELED]);

        //第2次Cancel
        $response = $this->post('/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'retry_succeed');

        //cancel后尝试confirm
        $data['action'] = 'CONFIRM';
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(400);
        $this->assertEquals($response->json()['message'],'Status is not DONE.');

    }

    public function testEcCommitSuccess()
    {
        $id = $this->getSubtaskId();

        $userModel = UserModel::query()->orderByDesc('id')->first();
        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'EC',
            'processor' => 'user@user.update',
            'subtask_id' => $id,
            'message_id' => '1',
            'data' => [
                'id'=>$userModel->id,
                'username'=>"test$id"
            ]
        ];

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertDatabaseHas($this->userModelTable,['id'=>$userModel->id,'username'=>$data['context']['data']['username']]);
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$id,'status'=>SubtaskStatus::DONE]);

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);
        $this->assertEquals($response->json()['status'],'retry_succeed');
    }

    public function testEcCommitFailed()
    {
        $id = $this->getSubtaskId();

        $userModels = UserModel::query()->orderBy('id')->get();
        $data['action'] = 'CONFIRM';
        $data['context'] = [
            'type' => 'EC',
            'processor' => 'user@user.update',
            'subtask_id' => $id,
            'message_id' => '1',
            'data' => [
                'id'=>$userModels[0]->id,
                'username'=>$userModels[1]->username
            ]
        ];

        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(500);
        $this->assertDatabaseMissing($this->userModelTable,['id'=>$userModels[0]->id,'username'=>$data['context']['data']['username']]);
        $this->assertDatabaseHas($this->subtaskTable,['id'=>$id,'status'=>SubtaskStatus::DOING]);

        $data['context'] = [
            'type' => 'EC',
            'processor' => 'user@user.update',
            'subtask_id' => $id,
            'message_id' => '1',
            'data' => [
                'id'=>$userModels[0]->id,
                'username'=>"test$id"
            ]
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(200);

        $this->assertEquals($response->json()['status'],'succeed');

    }

}
