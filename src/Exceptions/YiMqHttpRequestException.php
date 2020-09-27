<?php

namespace YiluTech\YiMQ\Exceptions;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use YiluTech\MicroApi\Adapters\MicroApiHttpRequest;

class YiMqHttpRequestException extends GuzzleRequestException  {
    
    public function __construct($e)
    {
        if ($e instanceof ConnectException) {
            $url = $e->getHandlerContext()['url'];
            $msg = "YiMQ can not connect $url ". $e->getMessage();
            return parent::__construct($msg,$e->getRequest(),null,$e->getPrevious());
        } elseif ($e instanceof RequestException && $e->getCode() == 0) {
            $msg = "YiMQ cURL error url malformed $this->uri" . $e->getMessage();
        } else {
            $msg = $e->getMessage();
        }

        return parent::__construct($msg,$e->getRequest(),$e->getResponse(),$e->getPrevious());
    }



    public function getData(){
        if(!$this->hasResponse()){
            return null;
        }

        $data = json_decode($this->getResponse()->getBody()->__toString(), 1);
        if(json_last_error() == JSON_ERROR_NONE){
            return $data;
        }else{
            return $this->getResponse()->getBody()->__toString();
        }
    }

}