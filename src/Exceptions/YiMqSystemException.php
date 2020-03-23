<?php


namespace YiluTech\YiMQ\Exceptions;


use Illuminate\Http\Exceptions\HttpResponseException;
class YiMqSystemException extends HttpResponseException
{
    protected $code = 500;
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
        $respone = response()->json($data,$this->getCode());
        parent::__construct($respone);
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

}