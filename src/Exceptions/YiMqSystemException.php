<?php


namespace YiluTech\YiMQ\Exceptions;


use RuntimeException;
use Psr\Log\LoggerInterface;
use Illuminate\Contracts\Support\Responsable;
use YiluTech\YiMQ\YiMqActor;

class YiMqSystemException extends RuntimeException implements Responsable
{
    protected $code = 500;
    public $data;
    public function __construct($message = "",$data=null)
    {
        parent::__construct($message);
        $this->data = [
            "message" => $message,
            "data" => $data,
        ];
        $this->response = response()->json($this->data,$this->getCode());
    }


    public function toResponse($request)
    {
        return $this->response;
    }
}