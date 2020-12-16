<?php
/**
 * Created by PhpStorm.
 * User: lishu
 * Date: 2018/11/20
 * Time: 4:03 PM
 */

namespace YiluTech\YiMQ;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use YiluTech\YiMQ\Constants\MessageStatus;
use YiluTech\YiMQ\Constants\TransactionMessageAction;
use YiluTech\YiMQ\Exceptions\YiMqHttpRequestException;
use YiluTech\YiMQ\Message\TransactionMessage;
use YiluTech\YiMQ\Mock\YiMqMockerBuilder;
use YiluTech\YiMQ\Mock\YiMqMockManager;
use YiluTech\YiMQ\Subtask\BcstSubtask;
use YiluTech\YiMQ\Subtask\EcSubtask;
use YiluTech\YiMQ\Subtask\TccSubtask;
use YiluTech\YiMQ\Subtask\XaSubtask;
use YiluTech\YiMQ\Models\Message As MessageModel;
class YiMqClient
{
    public $manager;
    private $uri;
    public $serviceName;
    public $guzzleClient;
    public $actions = [
        'create' => '/message/create',
        'subtask' => '/message/subtask',
        'prepare' => '/message/prepare',
        'confirm'=> '/message/confirm',
        'cancel' => '/message/cancel'
    ];
    public $mockManager = null;

    public function __construct(YiMqManager $manager,$serviceName,$config)
    {
        $this->manager = $manager;
        $this->serviceName = $serviceName;
        $this->uri= $config['uri'];
        $this->guzzleClient = new Client([
           'base_uri' => $this->uri,
            'timeout' => 3
        ]);
    }

    public function transaction($topic=null,$callback=null)
    {
        if($topic == null){
            return $this->getTransactionMessage();
        }

        return new TransactionMessage($this,$topic,$callback);

    }

    public function restoreByParentSubtask($processModel,$messageStatus){
        if($this->hasTransactionMessage()){
            throw new \Exception("MicroApi transaction message already exists.");
        }
        $parent_subtask_key = $processModel->producer .'@'. $processModel->id;
        $messageModel = MessageModel::lock('for update nowait')
            ->where('parent_subtask',$parent_subtask_key)
            ->where('status',$messageStatus)
            ->first();
        if(!$messageModel){
            return null;
        }

        $transaction = new TransactionMessage($this,$messageModel->topic,null);
        $transaction->model = $messageModel;
        $transaction->id = $messageModel->message_id;
        $this->setTransactionMessage($transaction);
        return $transaction;
    }

    public function tcc(String $processor): TccSubtask
    {
        if(!$this->hasTransactionMessage()){
            throw new \Exception('Not begin a yimq transaction');
        }
        return new TccSubtask($this,$this->getTransactionMessage(),$processor);
    }

    public function xa(String $processor): XaSubtask
    {
        if(!$this->hasTransactionMessage()){
            throw new \Exception('Not begin a yimq transaction');
        }
        return new XaSubtask($this,$this->getTransactionMessage(),$processor);
    }

//    public function prepare():TransactionMessage
//    {
//        if(!$this->hasTransactionMessage()){
//            throw new \Exception('Not begin a yimq transaction');
//        }
//        return $this->getTransactionMessage()->prepare();
//    }

    public function commit():TransactionMessage
    {
        if(!$this->hasTransactionMessage()){
            throw new \Exception('Not begin a yimq transaction');
        }
        return $this->getTransactionMessage()->commit();
    }
    public function rollback():TransactionMessage{
        if(!$this->hasTransactionMessage()){
            throw new \Exception('Not begin a yimq transaction');
        }
        return $this->getTransactionMessage()->rollback();
    }

    public function ec(String $processor): EcSubtask
    {
        if(!$this->hasTransactionMessage()){
            throw new \Exception('Not begin a yimq transaction');
        }
        return new EcSubtask($this,$this->getTransactionMessage(),$processor);
    }
    public function bcst(String $topic): BcstSubtask
    {
        if(!$this->hasTransactionMessage()){
            throw new \Exception('Not begin a yimq transaction');
        }
        return new BcstSubtask($this,$this->getTransactionMessage(),$topic);
    }




    public function mock():YiMqMockerBuilder
    {
        return new YiMqMockerBuilder($this);
    }

    public function setTransactionMessage(TransactionMessage $transactionMessage){
        //将来支持swoole的时候，由于manger是单例，所以transactionMessage应该绑定在request上，而不是manager上
        $this->manager->transactionMessage = $transactionMessage;
    }

    public function hasTransactionMessage(){
        return is_null($this->manager->transactionMessage) ? false : true;
    }
    public function getTransactionMessage(){
        return $this->manager->transactionMessage;
    }
    public function clearTransactionMessage(){
        $this->manager->transactionMessage = null;
    }

    public function getMockManager():YiMqMockManager
    {
        //将来支持swoole的时候，由于manger是单例，所以mocker应该绑定在request上，而不是manager上
        if($this->mockManager){
            return $this->mockManager;
        }
        return $this->mockManager = new YiMqMockManager($this);
    }


    public function callServer($action,$context=[]){
//        \Log::debug("Client: [$this->serviceName] call server <$action>");

        $context['actor'] = $this->manager->actorName;

        $logContent['action'] = $action;
        $logContent['context'] = $context;
        try {
            $requestOptions = [
                'json' => $context,
            ];
            if(isset($context['options']['timeout'])){
                $requestOptions['timeout'] = $context['options']['timeout']/1000+1;
            }
            $result = $this->guzzleClient->post($this->actions[$action],$requestOptions);
            \Log::info("YiMQ.Client",$logContent);
        } catch (\Exception $e) {

            $exception =  new YiMqHttpRequestException($e);
            $logContent = array_merge(
                ['message' => $exception->getMessage()],
                $logContent
            );

            if($exception->hasResponse()){
                $logContent['response'] = $exception->getData();
            }
            \Log::error("YiMQ.Client",$logContent);
            throw $exception;
        }

        return json_decode($result->getBody()->getContents(),true);

    }



}
