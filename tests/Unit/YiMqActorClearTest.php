<?php


namespace Tests\Unit;

use Tests\TestCase;
use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\MessageType;
use YiluTech\YiMQ\Constants\SubtaskStatus;
use YiluTech\YiMQ\Constants\SubtaskType;
use YiluTech\YiMQ\Models\Message as MessageModel;
use YiluTech\YiMQ\Models\Subtask as SubtaskModel;
use YiluTech\YiMQ\Services\YiMqActorClear;
use YiluTech\YiMQ\Models\ProcessModel;

class YiMqActorClearTest extends TestCase
{
    public function testClearSuccess(){

        $doneMessage =$this->createMessage(MessageStatus::DONE);
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$doneMessage->message_id,"status"=>MessageStatus::DONE]);

        $canceledMessage =$this->createMessage(MessageStatus::CANCELED);
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$canceledMessage->message_id,"status"=>MessageStatus::CANCELED]);
        $context = [
            'done_message_ids' => [$doneMessage->message_id],
            'canceled_message_ids' => [$canceledMessage->message_id],
            'process_ids' => []
        ];
        $yimqActorClear =  resolve(YiMqActorClear::class );
        $yimqActorClear->run($context);
        $this->assertDatabaseMissing($this->messageTable,['message_id'=>$doneMessage->message_id]);
        $this->assertDatabaseMissing($this->messageTable,['message_id'=>$canceledMessage->message_id]);
    }

    public function testClearMessageHaveLockedMessage(){
        $xid = "clear_test_1";
        $pdo = \DB::connection()->getPdo();

        $messageIds = [];
        for ($i =0;$i<6;$i++){//用这个删除，单元测试中，有任一一个锁定message，where in 条件大于6的时候，就会失败，所以采用了存储过程一个个删除
            $message =$this->createMessage(MessageStatus::DONE);
            $messageIds[] = $message->message_id;
        }

        $this->assertDatabaseHas($this->messageTable,['message_id'=>$messageIds[0],"status"=>MessageStatus::DONE]);



        try {

            $pdo->exec("XA START '$xid'");
            MessageModel::where('message_id',$messageIds[1])->lockForUpdate()->get();
            $pdo->exec("XA end '$xid'");
            $pdo->exec("XA PREPARE '$xid'");
            //actor clear会开启新的事务，所以把db重新连接，建立新的session
            \DB::reconnect();


            $context = [
                'done_message_ids' => $messageIds,
                'canceled_message_ids' => [],
                'process_ids' => []
            ];
            $yimqActorClear =  resolve(YiMqActorClear::class );
            $result = $yimqActorClear->run($context);
            $this->assertEquals($result['failed_done_message_ids'],[$messageIds[1]]);

            $this->assertDatabaseMissing($this->messageTable,['message_id'=>$messageIds[0]]);
            $this->assertDatabaseHas($this->messageTable,['message_id'=>$messageIds[1]]);
        }finally {
            $pdo->exec("XA commit '$xid'");
            $this->assertDatabaseHas($this->messageTable,['message_id'=>$messageIds[1]]);
        }
    }

    public function testClearMessageHaveCreateMessageUnCommit(){
        $xid = "clear_test_1";
        $pdo = \DB::connection()->getPdo();

        $message1 =$this->createMessage(MessageStatus::CANCELED);
        $this->assertDatabaseHas($this->messageTable,['message_id'=>$message1->message_id,"status"=>MessageStatus::CANCELED]);

        try {

            $pdo->exec("XA START '$xid'");
            $message2 =$this->createMessage(MessageStatus::CANCELED);
            $this->assertDatabaseHas($this->messageTable,['message_id'=>$message2->message_id,"status"=>MessageStatus::CANCELED]);
            $pdo->exec("XA end '$xid'");
            $pdo->exec("XA PREPARE '$xid'");
            //actor clear会开启新的事务，所以把db重新连接，建立新的session
            \DB::reconnect();


            $context = [
                'done_message_ids' => [],
                'canceled_message_ids' => [$message1->message_id,$message2->message_id],
                'process_ids' => []
            ];
            $yimqActorClear =  resolve(YiMqActorClear::class );
            $result = $yimqActorClear->run($context);
            $this->assertEquals($result['failed_canceled_message_ids'],[$message2->message_id]);

            $this->assertDatabaseMissing($this->messageTable,['message_id'=>$message1->message_id]);
            $this->assertDatabaseMissing($this->messageTable,['message_id'=>$message2->message_id]);
        }finally {
            $pdo->exec("XA commit '$xid'");
            $this->assertDatabaseHas($this->messageTable,['message_id'=>$message2->message_id]);
        }
    }

    public function testClearClearedMessage(){
        $context = [
            'done_message_ids' => ['3000'],
            'canceled_message_ids' => [],
            'process_ids' => []
        ];
        $yimqActorClear =  resolve(YiMqActorClear::class );
        $result = $yimqActorClear->run($context);
        $this->assertEquals($result['failed_done_message_ids'],[]);
    }


    public function testClearSubtaskOfMessage(){
        $xid = "clear_test_1";
        $pdo = \DB::connection()->getPdo();

        $doneMessage1 =$this->createMessage(MessageStatus::DONE);
        $doneMessage1Subtask1 = $this->createSubtask($doneMessage1->message_id,SubtaskStatus::DONE);
        $doneMessage1Subtask2 = $this->createSubtask($doneMessage1->message_id,SubtaskStatus::DONE);
        $doneMessage2 =$this->createMessage(MessageStatus::DONE);
        $doneMessage2Subtask1 = $this->createSubtask($doneMessage2->message_id,SubtaskStatus::DONE);

        try {

            $pdo->exec("XA START '$xid'");
            MessageModel::where('message_id',$doneMessage2->message_id)->lockForUpdate()->get();
            $pdo->exec("XA end '$xid'");
            $pdo->exec("XA PREPARE '$xid'");
            //actor clear会开启新的事务，所以把db重新连接，建立新的session
            \DB::reconnect();


            $context = [
                'done_message_ids' => [$doneMessage1->message_id,$doneMessage2->message_id],
                'canceled_message_ids' => [],
                'process_ids' => []
            ];
            $yimqActorClear =  resolve(YiMqActorClear::class );
            $result = $yimqActorClear->run($context);

            $this->assertEquals($result['failed_done_message_ids'],[$doneMessage2->message_id]);
            //检查message1
            $this->assertDatabaseMissing($this->messageTable,['message_id'=>$doneMessage1->message_id]);
            $this->assertDatabaseMissing($this->subtaskTable,['message_id'=>$doneMessage1->message_id]);
            //检查message2
            $this->assertDatabaseHas($this->messageTable,['message_id'=>$doneMessage2->message_id]);
            $this->assertDatabaseHas($this->subtaskTable,['message_id'=>$doneMessage2->message_id]);
        }finally {
            $pdo->exec("XA commit '$xid'");
        }
    }

    public function testClearProcessSuccess(){
        $process1 = $this->createProcess(SubtaskStatus::DONE);
        $process2 = $this->createProcess(SubtaskStatus::CANCELED);
        $context = [
            'done_message_ids' => [],
            'canceled_message_ids' => [],
            'process_ids' => [$process1->id,$process2->id]
        ];
        $yimqActorClear =  resolve(YiMqActorClear::class );
        $result = $yimqActorClear->run($context);
        $this->assertEquals($result['failed_process_ids'],[]);
    }


    public function testClearProcessHaveLockedPorcess(){
        $xid = "clear_test_1";
        $pdo = \DB::connection()->getPdo();

        $processIds = [];
        for ($i =0;$i<11;$i++){// 不用存储过程的话  process where in 条件大于11的时候，就会失败，所以采用了存储过程一个个删除
            $process =$this->createProcess(SubtaskStatus::DONE);
            $processIds[] = $process->id;
        }

        $this->assertDatabaseHas($this->processModelTable,['id'=>$processIds[0],"status"=>SubtaskStatus::DONE]);

        try {

            $pdo->exec("XA START '$xid'");
            ProcessModel::where('id',$processIds[1])->lockForUpdate()->get();
            $pdo->exec("XA end '$xid'");
            $pdo->exec("XA PREPARE '$xid'");
            //actor clear会开启新的事务，所以把db重新连接，建立新的session
            \DB::reconnect();

            $context = [
                'done_message_ids' => [],
                'canceled_message_ids' => [],
                'process_ids' => $processIds
            ];
            $yimqActorClear =  resolve(YiMqActorClear::class );
            $result = $yimqActorClear->run($context);
            $this->assertEquals($result['failed_process_ids'],[$processIds[1]]);

            $this->assertDatabaseMissing($this->processModelTable,['id'=>$processIds[0]]);
            $this->assertDatabaseHas($this->processModelTable,['id'=>$processIds[1]]);
        }finally {
            $pdo->exec("XA commit '$xid'");
            $this->assertDatabaseHas($this->processModelTable,['id'=>$processIds[1]]);
        }
    }


    public function testClearProcessHaveCreateProcessUnCommit(){
        $xid = "clear_test_1";
        $pdo = \DB::connection()->getPdo();

        $process1 =$this->createProcess(SubtaskStatus::CANCELED);
        $this->assertDatabaseHas($this->processModelTable,['id'=>$process1->id,"status"=>SubtaskStatus::CANCELED]);

        try {

            $pdo->exec("XA START '$xid'");
            $process2 =$this->createProcess(SubtaskStatus::CANCELED);
            $this->assertDatabaseHas($this->processModelTable,['id'=>$process2->id,"status"=>SubtaskStatus::CANCELED]);
            $pdo->exec("XA end '$xid'");
            $pdo->exec("XA PREPARE '$xid'");
            //actor clear会开启新的事务，所以把db重新连接，建立新的session
            \DB::reconnect();


            $context = [
                'done_message_ids' => [],
                'canceled_message_ids' => [],
                'process_ids' => [$process1->id,$process2->id]
            ];
            $yimqActorClear =  resolve(YiMqActorClear::class );
            $result = $yimqActorClear->run($context);
            $this->assertEquals($result['failed_process_ids'],[$process2->id]);

            $this->assertDatabaseMissing($this->processModelTable,['id'=>$process1->id]);
            $this->assertDatabaseMissing($this->processModelTable,['id'=>$process2->id]);
        }finally {
            $pdo->exec("XA commit '$xid'");
            $this->assertDatabaseHas($this->processModelTable,['id'=>$process2->id]);
        }
    }

    public function testClearClearedProcess(){
        $context = [
            'done_message_ids' => [],
            'canceled_message_ids' => [],
            'process_ids' => [2000]
        ];
        $yimqActorClear =  resolve(YiMqActorClear::class );
        $result = $yimqActorClear->run($context);
        $this->assertEquals($result['failed_process_ids'],[]);
    }



    public function createMessage($status){
        $messageModel = new MessageModel();
        $messageModel->message_id = $this->getMessageId();
        $messageModel->topic = 'test';
        $messageModel->type = MessageType::TRANSACTION;
        $messageModel->status = $status;
        $messageModel->save();
        return $messageModel;
    }

    public function createSubtask($message_id,$status){
        $subtaskModel = new SubtaskModel();
        $subtaskModel->subtask_id = $this->getSubtaskId();
        $subtaskModel->message_id = $message_id;
        $subtaskModel->type = SubtaskType::EC;
        $subtaskModel->status = $status;
        $subtaskModel->save();

    }
    public function createProcess($status){
        $processModel =  new ProcessModel();
        $id = $this->getProcessId();
        $processModel->id = $id;
        $processModel->message_id = $this->getMessageId();
        $processModel->type = SubtaskType::EC;
        $processModel->data = [];
        $processModel->processor="user.create";
        $processModel->status = $status;
        $processModel->save();
        $processModel->id = $id;
        return $processModel;
    }

}