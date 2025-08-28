<?php

declare(strict_types=1);

// ✅ DEBUGGING DESPUÉS de declare
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__FILE__) . '/error.log');

use Phalcon\Loader;
use Phalcon\Config;
use Phalcon\Di\FactoryDefault;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\View;
use Phalcon\Url;
use Phalcon\Db\Adapter\Pdo\Postgresql;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// ----------------------------------------------------------------------------
// Autoload
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

// Config
$di->setShared('config', function () {
    return include APP_PATH . '/config/config.php';
});

// Vista deshabilitada
$di->setShared('view', function () {
    $view = new View();
    $view->disable();
    return $view;
});

// URL
$di->setShared('url', function () use ($di) {
    $url = new Url();
    $url->setBaseUri($di->getShared('config')->app->baseUri);
    return $url;
});

// Base de datos
$di->setShared('db', function () use ($di) {
    $cfg = $di->getShared('config')->database->toArray();
    unset($cfg['adapter']);
    return new Postgresql($cfg);
});

// ✅ CORS DIRECTO (sin middleware problemático)
function handleCors(): void
{
    // ✅ PERMITIR CUALQUIER ORIGEN (IP, dominio, puerto)
    header("Access-Control-Allow-Origin: *");

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Origin, Accept');

    // ❌ IMPORTANTE: Comentar esta línea - No compatible con *
    // header('Access-Control-Allow-Credentials: true');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

handleCors();

// ----------------------------------------------------------------------------
// Aplicación
// ----------------------------------------------------------------------------
$application = new Application($di);
$eventsManager = new EventsManager();

// ✅ CARGAR SOLO EL ROUTER (sin CorsMiddleware)
require_once APP_PATH . '/config/router.php';

$application->setEventsManager($eventsManager);

// ----------------------------------------------------------------------------
// Manejo de errores mejorado
// ----------------------------------------------------------------------------
try {
    echo $application->handle($_SERVER['REQUEST_URI'])->getContent();
} catch (Throwable $e) {
    handleCors();
    http_response_code(500);
    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'code' => 500,
        'message' => 'Error interno: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5)
        ]
    ], JSON_PRETTY_PRINT);

    error_log("ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}
