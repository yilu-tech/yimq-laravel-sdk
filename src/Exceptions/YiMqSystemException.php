<?php


namespace YiluTech\YiMQ\Exceptions;


use RuntimeException;
use Psr\Log\LoggerInterface;
use Illuminate\Contracts\Support\Responsable;

class YiMqSystemException extends RuntimeException implements  Responsable
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

    /**
     * @inheritDoc
     */
    public function toResponse($request)
    {
        return $this->response;
    }
}