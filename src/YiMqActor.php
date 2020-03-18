<?php


namespace YiluTech\YiMQ;



class YiMqActor
{
    private $processorsMap;
    private $app;
    public function __construct(\App $app)
    {
        $this->app = $app;
        $this->processorsMap = config('yimq.processors');

    }

    public function try($context){
        $processor = $this->getProcessor($context['processor']);
        $processor->run($context);
        return ['status'=>'success'];

    }

    public function confirm($context){
        $processor = $this->getProcessor($context['processor']);
        return $processor->confirm($context);
    }

    public function cancel($context){
        $processor = $this->getProcessor($context['processor']);
        return $processor->cancel($context);
    }

    public function messageCheck(){

    }

    public function subtaskCheck($context){
        $processor = $this->getProcessor($context['processor']);
        $processor->subtaskCheck($context);
    }

    private function getProcessor($processor){
        if(!isset($this->processorsMap[$processor])){
            throw new \Exception("Processor <$processor> not exists");
        }
        return resolve($this->processorsMap[$processor]);
    }
}