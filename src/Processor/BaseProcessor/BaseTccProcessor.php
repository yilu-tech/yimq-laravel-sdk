<?php


namespace YiluTech\YiMQ\Processor\BaseProcessor;


abstract class BaseTccProcessor extends Processor
{

    public function runTry($context){
        $this->setContextToThis($context);
        $this->subtaskMatchProcessor($context['type']);
        $this->_runValidate();
        return $this->_runTry($context);
    }

    public function runConfirm($context)
    {
        $this->id = $context['id'];
       return  parent::runConfirm($context); // TODO: Change the autogenerated stub
    }

    public function runCancel($context){
        $this->id = $context['id'];
        $this->subtaskMatchProcessor($context['type']);
        return $this->_runCancel($context);
    }
}