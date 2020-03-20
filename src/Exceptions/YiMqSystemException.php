<?php


namespace YiluTech\YiMQ\Exceptions;


use Throwable;

class YiMqSystemException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}