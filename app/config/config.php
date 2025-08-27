<?php

use Phalcon\Config;

return new Config([
    'database' => [
        'adapter'  => 'Postgresql',
        'host'     => 'localhost',
        'username' => 'postgres',
        'password' => '241302',
        'dbname'   => 'rancing_salud_mental',
        // ❌ QUITAR ESTA LÍNEA: 'charset'  => 'utf8',
    ],
    'jwt' => [
        'secret' => 'tu_jwt_secret_key_super_segura_2024',
        'expire' => 86400, // 24 horas
    ],
    'app' => [
        'baseUri' => '/api-rancing-salud-mental/',
    ],
    'cors' => [
        'allowedOrigins' => ['*'], // Permite cualquier origen
    ],
]);
