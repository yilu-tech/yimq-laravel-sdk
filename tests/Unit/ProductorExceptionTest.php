<?php


namespace Tests\Unit;


use Tests\TestCase;
use YiluTech\YiMQ\Exceptions\YiMqSubtaskPrepareException;

class ProductorExceptionTest extends TestCase
{
    public function testAddXaSubtaskAutoThrowException()
    {
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->xa('user@user.create')->reply(400,['message'=>'user exsits.']);
        \YiMQ::mock()->rollback()->reply(200);
        $message = \YiMQ::transaction('user.create',function (){

            try {
                $xaSubtask = \YiMQ::xa('user@user.create')->data([])->prepare();
            }catch (YiMqSubtaskPrepareException $e){
                $this->assertEquals($e->getStatusCode(),400);
                $this->assertEquals($e->getResult(),['message'=>'user exsits.']);
            }

        })->begin();

    }

    public function testAddXaSubtaskNoThrowException()
    {
        \YiMQ::mock()->transaction('user.create')->reply(200);
        \YiMQ::mock()->xa('user@user.create')->reply(400,['message'=>'user exsits.']);
        $message = \YiMQ::transaction('user.create',function (){

            $xaSubtask = \YiMQ::xa('user@user.create')->data([])->throw(false)->prepare();
            if(!$xaSubtask->prepareSuccessful()){
                $this->assertEquals($xaSubtask->prepareStatus,400);
                $this->assertEquals($xaSubtask->prepareData,['message'=>'user exsits.']);
            }

        })->begin();


    }
}