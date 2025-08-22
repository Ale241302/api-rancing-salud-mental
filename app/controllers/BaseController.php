<?php

use Phalcon\Mvc\Controller;

class BaseController extends Controller
{
    protected function jsonResponse($data, $code = 200, $message = 'OK')
    {
        $this->response->setStatusCode($code, $message);
        $this->response->setContentType('application/json', 'UTF-8');

        $response = [
            'success' => $code < 400,
            'code' => $code,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $this->response->setJsonContent($response);
        return $this->response;
    }

    protected function jsonError($message, $code = 400)
    {
        return $this->jsonResponse(null, $code, $message);
    }

    protected function getJsonInput()
    {
        return json_decode($this->request->getRawBody(), true);
    }
}
