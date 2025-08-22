<?php
// public/index.php
declare(strict_types=1);

use Phalcon\Config;
use Phalcon\Di\FactoryDefault;
use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Loader;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Http\Response;
use Phalcon\Http\Request;
use Phalcon\Url;
// -------------------------------------------------
// Autoload composer + carpetas de la app
// -------------------------------------------------
require dirname(__DIR__) . '/vendor/autoload.php';

$loader = new Loader();
$loader->registerDirs(
    [
        dirname(__DIR__) . '/app/controllers/',
        dirname(__DIR__) . '/app/models/',
        dirname(__DIR__) . '/app/middleware/',
        dirname(__DIR__) . '/app/library/',
    ]
)->register();

// -------------------------------------------------
// DI container
// -------------------------------------------------
$di = new FactoryDefault();

use Phalcon\Mvc\View;

$di->setShared('view', function () {
    $view = new View();   // objeto requerido por el framework
    $view->disable();     // no genera ninguna salida HTML
    return $view;
});

/**
 * Config global (carga tu propio archivo YML/ENV si lo tienes)
 */
$di->setShared('config', function () {
    return new Config([
        'app' => [
            'baseUri' => '/api-rancing-salud-mental/',   // ← aquí
        ],
        'cors' => [
            'allowedOrigins' => ['http://localhost:5173'],
        ],
    ]);
});

$di->setShared('url', function () use ($di) {
    $url = new Url();
    $url->setBaseUri($di->getShared('config')->app->baseUri);
    return $url;
});
/**
 * Servicios clásicos: router, db, seguridad, etc.
 *  — aquí solo se muestra Router como ejemplo —
 */
$di->setShared('router', function () {
    $router = new \Phalcon\Mvc\Router(false);
    $router->removeExtraSlashes(true);

    $router->addPost(
        '/api-rancing-salud-mental/api/auth/register',   // ← cambia aquí
        [
            'controller' => 'auth',
            'action'     => 'register',
        ]
    );


    // … agrega las demás rutas
    return $router;
});

// -------------------------------------------------
// Middleware CORS
// -------------------------------------------------
class CorsMiddleware
{
    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher): bool
    {
        /** @var Request $request */
        $request = $dispatcher->getDI()->getShared('request');
        /** @var Response $response */
        $response = $dispatcher->getDI()->getShared('response');
        /** @var Config $config */
        $config = $dispatcher->getDI()->getShared('config');

        $originHeader = $request->getHeader('Origin');
        $allowedOrigins = $config->cors->allowedOrigins->toArray();

        // Si el origen está autorizado, envía encabezados CORS específicos
        if ($originHeader && in_array($originHeader, $allowedOrigins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $originHeader);
        }

        // Siempre: métodos, headers y credenciales
        $response
            ->setHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS')
            ->setHeader(
                'Access-Control-Allow-Headers',
                'Content-Type, Authorization, X-Requested-With'
            )
            ->setHeader('Access-Control-Allow-Credentials', 'true')
            ->setHeader('Vary', 'Origin'); // evita mezcla de caché por proxy

        // Pre-flight
        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(204, 'No Content')->send();
            return false; // corta la petición
        }

        return true;
    }
}

// -------------------------------------------------
// Aplicación MVC
// -------------------------------------------------
$application   = new Application($di);
$eventsManager = new EventsManager();

// Adjunta el middleware en el evento adecuado
$eventsManager->attach('dispatch:beforeExecuteRoute', new CorsMiddleware());

// Manejo de excepciones no atrapadas → JSON
$eventsManager->attach('application:beforeException', function (
    Event $event,
    Application $app,
    \Throwable $exception
) {
    /** @var Response $response */
    $response = $app->di->getShared('response');

    $response->setStatusCode(
        $exception->getCode() >= 400 ? $exception->getCode() : 500,
        'Error'
    )->setJsonContent(
        [
            'success' => false,
            'message' => $exception->getMessage(),
        ]
    )->send();

    // evita que Phalcon siga procesando
    return false;
});

$application->setEventsManager($eventsManager);

// -------------------------------------------------
// Despacha la petición
// -------------------------------------------------
try {
    $response = $application->handle($_SERVER['REQUEST_URI']);
    $response->send();
} catch (\Throwable $e) {
    // fallback por si falla el beforeException
    $di->getShared('response')
        ->setStatusCode(500, 'Internal Server Error')
        ->setJsonContent(
            [
                'success' => false,
                'message' => 'Unhandled exception: ' . $e->getMessage(),
            ]
        )
        ->send();
}
