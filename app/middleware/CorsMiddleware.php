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

        // ✅ PERMITIR CUALQUIER ORIGEN - Sin restricciones
        $response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Origin, Accept, Cache-Control, X-HTTP-Method-Override')
            ->setHeader('Access-Control-Max-Age', '86400'); // Cache preflight 24h

        // ✅ MANEJAR PREFLIGHT OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(200)->send(); // Cambiar de 204 a 200
            return false;
        }

        return true;
    }
}
