<?php


namespace YiluTech\YiMQ;



class YiMqManager
{
    /**
     * The application instance.
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    private $clients = [];

    public $transactionMessage = null;
    public $actorName=null;

    /**
     * 创建MicroApi实例
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->actorName = $this->app['config']["yimq.actor_name"];
    }


    /**
     * 获取请求构造器
     * @param string $name
     * @return MicroApiRequestBuilder
     */
    public function client($name = 'default'):YiMqClient
    {

        $messageBuilder = $this->getClient($name);
        

        return $messageBuilder;
    }

    public function getClient($name):YiMqClient
    {
        if(!isset($this->clients[$name])){
            $this->clients[$name] = new YiMqClient($this,$name,$this->getClientConfig($name));
        }
        return $this->clients[$name];

    }

    /**
     * 获取网关配置
     * @param $name
     * @return array
     */
    private function getClientConfig($name):array
    {

        $config =  $this->app['config']["yimq.services.$name"];
        if(!$config){
            throw new \InvalidArgumentException("YiMQ services [{$name}] not configured.");
        }
        return $config;
    }




    /**
     * Dynamically pass methods to the default connection.
     * 通过动态方法构造默认Gateway对应的RequestBuilder
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return YiMqBuilder
     */
    public function __call($method, $parameters)
    {
        //HTTP请求构造器
        $yiMqBuilder =  $this->client()->$method(...$parameters);
        return $yiMqBuilder;

    }

}
