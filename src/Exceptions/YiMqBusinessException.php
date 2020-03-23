<?php


namespace YiluTech\YiMQ\Exceptions;


use Psr\Log\LoggerInterface;

class YiMqBusinessException extends YiMqSystemException
{
    protected $code = 400;

    public function report()
    {


        try {
            $logger = app()->make(LoggerInterface::class);
        } catch (Exception $ex) {
            throw $this;
        }

        $logger->debug(
            $this->getMessage(),
            ['exception' => $this]
        );
    }

}