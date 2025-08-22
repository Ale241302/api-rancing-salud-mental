<?php

// public/index.php
declare(strict_types=1);

use Phalcon\Loader;
use Phalcon\Config;
use Phalcon\Di\FactoryDefault;
use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Http\Request;
use Phalcon\Http\Response;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\View;
use Phalcon\Url;
use Phalcon\Db\Adapter\Pdo\Postgresql;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// ----------------------------------------------------------------------------
// Autoload Composer y carpetas de la app
// ----------------------------------------------------------------------------
require BASE_PATH . '/vendor/autoload.php';

$loader = new Loader();

$loader->registerDirs([
    APP_PATH . '/controllers/',
    APP_PATH . '/models/',
    APP_PATH . '/middleware/',
    APP_PATH . '/library/',
])->register();


// ----------------------------------------------------------------------------
// Contenedor DI
// ----------------------------------------------------------------------------
$di = new FactoryDefault();

/**
 * Config: reunimos el archivo app/config/config.php (credenciales DB y JWT)
 * y añadimos ajustes de la app (baseUri) y de CORS.
 */
$di->setShared('config', function () {
    $core = include APP_PATH . '/config/config.php';      // contiene 'database' y 'jwt'
    $extra = new Config([
        'app'  => ['baseUri' => '/api-rancing-salud-mental/'],
        'cors' => ['allowedOrigins' => ['http://localhost:5173']],
    ]);
    return $core->merge($extra);
});

// ----------------------------------------------------------------------------
// Servicios DI
// ----------------------------------------------------------------------------

// 1) Vista deshabilitada (evitamos el error con 'view')
$di->setShared('view', function () {
    $view = new View();
    $view->disable();
    return $view;
});

// 2) URL (baseUri usado por el router internamente)
$di->setShared('url', function () use ($di) {
    $url = new Url();
    $url->setBaseUri($di->getShared('config')->app->baseUri);
    return $url;
});

// 3) Base de datos (PostgreSQL)
$di->setShared('db', function () use ($di) {

    // Tomamos los datos del archivo app/config/config.php
    $cfg = $di->getShared('config')->database->toArray();

    // Elimina la entrada 'adapter' si existe
    unset($cfg['adapter']);

    return new Postgresql($cfg);
});
// 4) Router
$di->setShared('router', function () {
    $router = new Router(false);
    $router->removeExtraSlashes(true);

    // Endpoint de registro
    $router->addPost(
        '/api-rancing-salud-mental/api/auth/register',
        [
            'controller' => 'auth',
            'action'     => 'register',
        ]
    );
    // ─── LOGIN ──────────────────────────────────────────────
    $router->addPost(
        '/api-rancing-salud-mental/api/auth/login',
        [
            'controller' => 'auth',
            'action'     => 'login',
        ]
    );
    $router->addGet('/api-rancing-salud-mental/api/auth/profile', [
        'controller' => 'auth',
        'action'     => 'profile',
    ]);


    // Puedes añadir más rutas aquí …

    return $router;
});


// ----------------------------------------------------------------------------
// Middleware CORS
// ----------------------------------------------------------------------------
class CorsMiddleware
{
    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher): bool
    {
        $di        = $dispatcher->getDI();
        $request   = $di->getShared('request');
        $response  = $di->getShared('response');
        $config    = $di->getShared('config');
        $origin    = $request->getHeader('Origin');
        $whitelist = $config->cors->allowedOrigins->toArray();

        if ($origin && in_array($origin, $whitelist, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        }

        $response
            ->setHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->setHeader('Access-Control-Allow-Credentials', 'true')
            ->setHeader('Vary', 'Origin');

        // Pre-flight
        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(204, 'No Content')->send();
            return false;  // detiene el flujo
        }
        return true;
    }
}

// ----------------------------------------------------------------------------
// Boot de la aplicación
// ----------------------------------------------------------------------------
$application   = new Application($di);
$eventsManager = new EventsManager();

// Inyectamos middleware y manejador de excepciones
$eventsManager->attach('dispatch:beforeExecuteRoute', new CorsMiddleware());

$eventsManager->attach('application:beforeException', function (
    Event $event,
    Application $app,
    Throwable $ex
) {
    $resp = $app->di->getShared('response');
    $resp->setStatusCode($ex->getCode() >= 400 ? $ex->getCode() : 500, 'Error')
        ->setJsonContent([
            'success' => false,
            'message' => $ex->getMessage(),
        ])
        ->send();
    return false;   // evita que Phalcon siga procesando
});
$eventsManager->attach('application:beforeSendResponse', function ($event, $app, $response) {
    $origin = $app->di->getShared('request')->getHeader('Origin');
    if ($origin === 'http://localhost:5173') {
        $response->setHeader('Access-Control-Allow-Origin', $origin)
            ->setHeader('Access-Control-Allow-Credentials', 'true')
            ->setHeader('Vary', 'Origin');
    }
});
$application->setEventsManager($eventsManager);

// ----------------------------------------------------------------------------
// Despacho
// ----------------------------------------------------------------------------
try {
    $application->handle($_SERVER['REQUEST_URI'])->send();
} catch (Throwable $e) {
    $di->getShared('response')
        ->setStatusCode(500, 'Internal Server Error')
        ->setJsonContent([
            'success' => false,
            'message' => 'Unhandled exception: ' . $e->getMessage(),
        ])
        ->send();
}
