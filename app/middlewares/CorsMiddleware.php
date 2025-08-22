<?php
class CorsMiddleware
{
    public function beforeHandleRoute(\Phalcon\Events\Event $event, \Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $origin = 'http://localhost:5173'; // coloca aquí los orígenes permitidos
        $response = $dispatcher->getDI()->getShared('response');

        $response->setHeader('Access-Control-Allow-Origin', $origin)
            ->setHeader('Vary', 'Origin') // para que el proxy/ CDN no mezcle orígenes
            ->setHeader('Access-Control-Allow-Credentials', 'true')
            ->setHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        // atajar la pre-flight
        if ($dispatcher->getDI()->getShared('request')->getMethod() === 'OPTIONS') {
            $response->setStatusCode(204, 'No Content')->send();
            return false; // detiene el flujo
        }
    }
}
