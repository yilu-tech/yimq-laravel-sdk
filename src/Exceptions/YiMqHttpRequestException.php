<?php

namespace YiluTech\YiMQ\Exceptions;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use YiluTech\MicroApi\Adapters\MicroApiHttpRequest;

class YiMqHttpRequestException extends GuzzleRequestException  {
    
    public function __construct($msg,GuzzleRequestException $e = null)
    {
        if(!$e){
            return $this->message = $msg;
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