<?php


namespace Tests\Services;


use YiluTech\YiMQ\Processor\XaProcessor;

class ExceptionProcessor extends XaProcessor
{

    protected function validate($validator)
    {
        $validator([
           'test'=>'required'
        ]);
    }

    function prepare()
    {
        if($this->data['test'] == 'general'){
            throw new \Exception('general');
        }
    }
}