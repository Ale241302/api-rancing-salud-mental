<?php
// app/config/router.php

use Phalcon\Mvc\Router;

$router = new Router(false);
$router->removeExtraSlashes(true);

// =============================================
// RUTAS DE AUTENTICACIÓN
// =============================================

$router->addPost('/api-rancing-salud-mental/api/auth/register', [
    'controller' => 'auth',
    'action'     => 'register',
]);

$router->addPost('/api-rancing-salud-mental/api/auth/login', [
    'controller' => 'auth',
    'action'     => 'login',
]);

$router->addGet('/api-rancing-salud-mental/api/auth/profile', [
    'controller' => 'auth',
    'action'     => 'profile',
]);

$router->addPut('/api-rancing-salud-mental/api/auth/profile', [
    'controller' => 'auth',
    'action'     => 'updateProfile',
]);

$router->addPost('/api-rancing-salud-mental/api/auth/change-password', [
    'controller' => 'auth',
    'action'     => 'changePassword',
]);

$router->addPost('/api-rancing-salud-mental/api/auth/validate-token', [
    'controller' => 'auth',
    'action'     => 'validateToken',
]);

$router->addPost('/api-rancing-salud-mental/api/auth/logout', [
    'controller' => 'auth',
    'action'     => 'logout',
]);

$router->addPost('/api-rancing-salud-mental/api/auth/refresh-token', [
    'controller' => 'auth',
    'action'     => 'refreshToken',
]);

// =============================================
// RUTAS DE EVENTOS
// =============================================

$router->addGet('/api-rancing-salud-mental/api/auth/listar-eventos', [
    'controller' => 'auth',
    'action'     => 'listareventos',
]);

$router->addGet('/api-rancing-salud-mental/api/auth/evento/{id:[0-9]+}', [
    'controller' => 'auth',
    'action'     => 'evento',
]);

$router->addPost('/api-rancing-salud-mental/api/auth/asignar-ponente-evento', [
    'controller' => 'auth',
    'action'     => 'asignarPonentEvento',
]);
$router->addPost('/api-rancing-salud-mental/api/auth/crear-tarjeta', [
    'controller' => 'auth',
    'action'     => 'creartarjeta',
]);
$router->addPost('/api-rancing-salud-mental/api/auth/registro-evento', [
    'controller' => 'auth',
    'action'     => 'registroevento',
]);

$router->addPost(
    '/api-rancing-salud-mental/api/auth/listar-compras',
    [
        'controller' => 'auth',
        'action'     => 'listarcompras'
    ]
);

// Registrar el router en el DI
$di->setShared('router', function () use ($router) {
    return $router;
});
