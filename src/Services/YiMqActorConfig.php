<?php


namespace YiluTech\YiMQ\Services;


use YiluTech\YiMQ\Exceptions\YiMqSystemException;

class YiMqActorConfig
{
    protected $processors = [];
    protected $broadcastTopics = [];
    protected $broadcastListeners = [];
    protected $schedules = [];
    function get(){
        if(!is_array(config('yimq.processors'))){
            throw new YiMqSystemException('yimq config processors define error.');
        }
        foreach (config('yimq.processors') as $alias => $processorClass){
            $processorObject = resolve($processorClass);
            $item = $processorObject->getOptions();
            $item['processor'] = config('yimq.actor_name').'.'.$alias;
            array_push($this->processors,$item);
        }

        if(!is_array(config('yimq.broadcast_listeners'))){
            throw new YiMqSystemException('yimq config broadcast_listeners define error.');
        }
        foreach (config('yimq.broadcast_listeners',[]) as $class => $topic){
            $listenerObject =  resolve($class);
            $item['processor'] = $class;
            $item['topic'] = $topic;
            $item = array_merge($item,$listenerObject->getOptions());
            array_push($this->broadcastListeners,$item);
        }

        return [
            'actor_name' => config('yimq.actor_name'),
            'processors' => $this->processors,
            'broadcast_listeners' => $this->broadcastListeners
        ];




    }

}