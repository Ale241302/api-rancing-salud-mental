<?php

use Phalcon\Db\Adapter\Pdo\Postgresql;
use Phalcon\Security;
use Phalcon\Crypt;

// Base de datos PostgreSQL
$di->setShared('db', function () {
    return new Postgresql([
        'host'     => 'localhost',
        'username' => 'postgres',
        'password' => '241302',
        'dbname'   => 'rancing_salud_mental',
        'charset'  => 'utf8',
        'port'     => 5432
    ]);
});

// Security
$di->setShared('security', function () {
    $security = new Security();
    $security->setWorkFactor(12);
    return $security;
});

// Crypt
$di->setShared('crypt', function () {
    $crypt = new Crypt();
    $crypt->setKey('tu_clave_secreta_muy_larga_y_segura_32_caracteres');
    return $crypt;
});

// Configuración
$di->setShared('config', function () {
    return include APP_PATH . '/config/config.php';
});
