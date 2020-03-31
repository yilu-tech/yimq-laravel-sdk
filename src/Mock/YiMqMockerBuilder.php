<?php


namespace YiluTech\YiMQ\Mock;


use YiluTech\YiMQ\Mock\Mockers\YiMqTccSubtaskMocker;
use YiluTech\YiMQ\Mock\Mockers\YiMqTransactionMessageMocker;
use YiluTech\YiMQ\Mock\Mockers\YiMqXaSubtaskMocker;


class YiMqMockerBuilder
{
    public $client;
    public function __construct($client)
    {
        $this->client = $client;
    }

    public function tcc($processor):YiMqTccSubtaskMocker{
        return new YiMqTccSubtaskMocker($this->client,$processor);
    }
    public function xa($processor):YiMqTccSubtaskMocker{
        return new YiMqXaSubtaskMocker($this->client,$processor);
    }
    public function transaction($topic):YiMqTransactionMessageMocker{
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