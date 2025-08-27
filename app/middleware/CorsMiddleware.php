<?php
// app/middleware/CorsMiddleware.php

use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;

class CorsMiddleware
{
    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher): bool
    {
        $di = $dispatcher->getDI();
        $request = $di->getShared('request');
        $response = $di->getShared('response');
        $origin = $request->getHeader('Origin');

        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:3000',
            'http://127.0.0.1:5173'
        ];

        if ($origin && in_array($origin, $allowedOrigins)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        } else {
            $response->setHeader('Access-Control-Allow-Origin', '*');
        }

        $response
            ->setHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->setHeader('Access-Control-Allow-Credentials', 'true');

        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(204)->send();
            return false;
        }

        return true;
    }
}
