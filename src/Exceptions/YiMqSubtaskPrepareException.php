<?php


namespace YiluTech\YiMQ\Exceptions;


use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class YiMqSubtaskPrepareException extends RuntimeException
{
    protected $code = 500;
    protected $response;
    protected $result;
    protected $statusCode;
    public function __construct($message,$result,$code)
    {
        parent::__construct($message);
        $this->result = $result;
        $this->statusCode = $code;
        $data = [
            "message" => $message,
            "result" => $this->result,
        ];
        $this->response = response()->json($data,$this->getCode());

    }

    public function getResult(){
        return $this->result;
    }
    public function getStatusCode(){
        return $this->statusCode;
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

        $logger->debug(
            $this->getMessage(),
            ['exception' => $this]
        );
    }

}