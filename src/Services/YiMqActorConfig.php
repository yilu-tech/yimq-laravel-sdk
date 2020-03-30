<?php


namespace YiluTech\YiMQ\Services;


class YiMqActorConfig
{
    protected $processors = [];
    protected $broadcastTopics = [];
    protected $broadcastListeners = [];
    protected $schedules = [];
    function get(){
        foreach (config('yimq.processors') as $alias => $processorClass){
            $processorObject = resolve($processorClass);
            $item = $processorObject->getOptions();
            $item['processor'] = config('yimq.actor_name').'.'.$alias;
            array_push($this->processors,$item);
        }


        foreach (config('yimq.broadcast_listeners') as $class => $topic){
            $listenerObject =  resolve($class);
            $item['processor'] = $class;
            $item['topic'] = $topic;
            $item = array_merge($item,$listenerObject->getOptions());
            array_push($this->broadcastListeners,$item);
        }

        return [
            'processors' => $this->processors,
            'broadcast_listeners' => $this->broadcastListeners
        ];




    }

}