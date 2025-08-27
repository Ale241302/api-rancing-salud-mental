<?php

use Phalcon\Mvc\Controller;

class BaseController extends Controller
{
    protected function jsonResponse($data = null, int $code = 200, string $msg = '')
    {
        $resp = $this->response;

        // CORS headers
        $origin = $this->request->getHeader('Origin');
        if ($origin) {
            $resp->setHeader('Access-Control-Allow-Origin', $origin)
                ->setHeader('Access-Control-Allow-Credentials', 'true')
                ->setHeader('Vary', 'Origin');
        } else {
            $resp->setHeader('Access-Control-Allow-Origin', '*');
        }

        return $resp
            ->setStatusCode($code)
            ->setJsonContent([
                'success' => $code < 400,
                'code'    => $code,
                'message' => $msg,
                'data'    => $data,
            ]);
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
