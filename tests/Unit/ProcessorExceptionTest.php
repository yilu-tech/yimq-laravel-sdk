<?php


namespace Tests\Unit;


use Tests\TestCase;

class ProcessorExceptionTest extends TestCase
{
    function testGeneral(){
        $id = $this->getProcessId();
        $processor = 'user.exception';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'XA',
            'processor' => $processor,
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'username'=>"test$id",
                'test' => 'general'
            ]
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(500);
        $response->assertJson(['message'=>'general']);
    }
    function testValidate(){
        $id = $this->getProcessId();
        $processor = 'user.exception';
        $data['action'] = 'TRY';
        $data['context'] = [
            'type' => 'XA',
            'processor' => $processor,
            'id' => $id,
            'message_id' => '1',
            'data' => [
                'username'=>"test$id",
//                'test' => 'general'
            ]
        ];
        $response = $this->json('POST','/yimq',$data);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['test']);
    }
}