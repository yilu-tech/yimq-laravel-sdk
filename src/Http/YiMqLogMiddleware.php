<?php


namespace YiluTech\YiMQ\Http;

use Closure;
use Illuminate\Support\Arr;

class YiMqLogMiddleware
{
    public function handle($request, Closure $next)
    {


        $response = $next($request);

        //成功: 打印请求和响应日志
        if(empty($response->exception)){
            return $this->successResponse($request,$response);
        }



        //业务错误
        if($response->getStatusCode() < 500){
            return $this->businessExceptionResponse($request,$response);
        }


        return $this->systemExceptionResponse($request,$response);

    }

    protected function successResponse($request,$response){
        $logContent = [
            "action" => $request->input('action'),
            "context" => $request->input('context'),
            "response" => $response->getOriginalContent()
        ];

        \Log::info("YiMQ.Actor.Success",$logContent);
        return $response;
    }

    protected function businessExceptionResponse($request,$response){
        $logContent = [
            "action" => $request->input('action'),
            "context" => $request->input('context'),
            "response" => $response->getOriginalContent()
        ];

        $exceptionMessage = $response->exception->getMessage();
        \Log::info("YiMQ.Actor.Business: $exceptionMessage",$logContent);

        return $response;
    }



    protected function systemExceptionResponse($request,$response){
        $logContent = [
            "action" => $request->input('action'),
            "context" => $request->input('context'),
            "response" => $response->getOriginalContent(),
            "exception" =>$response->exception,
        ];

        $exceptionMessage = $response->exception->getMessage();
        \Log::error("YiMQ.Actor.System: $exceptionMessage",$logContent);

        $responseContent = array_merge(
            $response->getOriginalContent(),
            $this->convertExceptionToArray($response->exception)
        );


        return  response()->json($responseContent,$response->status());

    }



    protected function convertExceptionToArray(\Throwable $e)
    {
        return [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
//            'trace' => collect($e->getTrace())->map(function ($trace) {
//                return Arr::except($trace, ['args']);
//            })->all(),
        ];
    }
}