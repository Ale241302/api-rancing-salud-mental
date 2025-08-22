<?php

use Phalcon\Config;

return new Config([
    'database' => [
        'host'     => '127.0.0.1',   // o 'localhost'
        'port'     => 5432,
        'username' => 'postgres',
        'password' => '241302',
        'dbname'   => 'rancing_salud_mental',

        // Opcionales y válidos en Postgres:
        // 'schema' => 'public',
        // 'client_encoding' => 'UTF8',

        // Opciones PDO recomendadas
        'options'  => [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ],
    ],
    'jwt' => [
        'secret' => 'tu_jwt_secret_key_super_segura_2024',
        'expire' => 86400, // 24 horas
    ],
]);
