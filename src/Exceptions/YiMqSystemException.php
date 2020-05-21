<?php


namespace YiluTech\YiMQ\Exceptions;


use RuntimeException;
use Psr\Log\LoggerInterface;

class YiMqSystemException extends RuntimeException
{
    protected $code = 500;
    protected $response;
    public function __construct($message = "",$data=null)
    {
        $data = [
            "message" => $message,
            "data" => $data,
            "stack" => $this->getTrace()
        ];
        array_unshift($data['stack'],[
            "file" => $this->getFile(),
            "line" => $this->getLine()
        ]);
        $this->response = response()->json($data,$this->getCode());
        parent::__construct($message);
    }

    /**
     * Get the underlying response instance.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    public function report()
    {


        try {
            $logger = app()->make(LoggerInterface::class);
        } catch (Exception $ex) {
            throw $this;
        }

        $logger->error(
            $this->getMessage(),
            ['exception' => $this]
        );
    }

    public function render($request){
        return $this->response;
    }

}