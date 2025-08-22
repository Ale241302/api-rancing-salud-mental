<?php

// Rutas de autenticación
$app->post('/api/auth/register', function () use ($app) {
    $controller = new AuthController();
    $controller->setDI($app->getDI());
    return $controller->registerAction();
});

$app->post('/api/auth/login', function () use ($app) {
    $controller = new AuthController();
    $controller->setDI($app->getDI());
    return $controller->loginAction();
});

$app->get('/api/auth/profile', function () use ($app) {
    $controller = new AuthController();
    $controller->setDI($app->getDI());
    return $controller->profileAction();
});

// Ruta por defecto
$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    $app->response->setJsonContent([
        'success' => false,
        'message' => 'Endpoint no encontrado'
    ]);
    return $app->response;
});
