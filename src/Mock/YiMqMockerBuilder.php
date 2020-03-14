<?php


namespace YiluTech\YiMQ\Mock;


use YiluTech\YiMQ\Mock\Mockers\YiMqTccSubtaskMocker;
use YiluTech\YiMQ\Mock\Mockers\YiMqTransactionMessageMocker;


class YiMqMockerBuilder
{
    public $client;
    public function __construct($client)
    {
        $this->client = $client;
    }

    public function tcc($processer):YiMqTccSubtaskMocker{
        return new YiMqTccSubtaskMocker($this->client,$processer);
    }
    public function topic($topic):YiMqTransactionMessageMocker{
        $mocker =  new YiMqTransactionMessageMocker($this->client);
        $mocker->setTopic($topic);
        return $mocker;

    }
    public function prepare():YiMqTransactionMessageMocker{
        $mocker =  new YiMqTransactionMessageMocker($this->client);
        $mocker->prepare();
        return $mocker;
    }
    public function commit():YiMqTransactionMessageMocker{
        $mocker =  new YiMqTransactionMessageMocker($this->client);
        $mocker->commit();
        return $mocker;
    }
    public function rollback():YiMqTransactionMessageMocker{
        $mocker =  new YiMqTransactionMessageMocker($this->client);
        $mocker->rollback();
        return $mocker;
    }

}