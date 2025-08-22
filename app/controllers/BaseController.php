<?php

use Phalcon\Mvc\Controller;

class BaseController extends Controller
{
    protected function jsonResponse($data = null, int $code = 200, string $msg = '')
    {
        /** @var \Phalcon\Http\Response $resp */
        $resp = $this->response;

        // 1. Cabeceras CORS definitivas
        $origin = $this->request->getHeader('Origin');
        if ($origin === 'http://localhost:5173') {
            $resp->setHeader('Access-Control-Allow-Origin', $origin)
                ->setHeader('Access-Control-Allow-Credentials', 'true')
                ->setHeader('Vary', 'Origin');
        }

        // 2. Cuerpo JSON
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
