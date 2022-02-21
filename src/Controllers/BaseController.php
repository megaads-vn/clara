<?php

namespace Megaads\Clara\Controllers;

class BaseController
{
    protected function success($data = [], $message = '', $code = 200)
    {
        return [
            'data' => $data,
            'message' => $message,
            'code' => $code,
            'status' => 'successful'
        ];
    }

    protected function error($message = 'Bad Request', $code = 400)
    {
        return [
            'message' => $message,
            'code' => $code,
            'status' => 'fail'
        ];
    }

    protected function getDate($input)
    {
        if(preg_match("/\d{4}-\d{2}-\d{2}/", $input, $match)) {
            if (is_array($match)){
                return $match[0] ?? '';
            }

            return $match;
        }

        return "";
    }

    protected function validateResource($rsrc)
    {
        if (!in_array($rsrc, ['requests', 'performance', 'devices', 'users'])) {
            throw new \Exception("$rsrc resource not found!");
        }
    }
}