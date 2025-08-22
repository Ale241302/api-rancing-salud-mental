<?php
return new \Phalcon\Config([
    'database' => [
        'adapter'  => 'Postgresql',
        'host'     => 'localhost',
        'username' => 'postgres',
        'password' => '241302',
        'dbname'   => 'rancing_salud_mental',
        'charset'  => 'utf8',
        'port'     => 5432
    ],
    'jwt' => [
        'secret' => 'tu_jwt_secret_key_super_segura_2024',
        'expire' => 86400 // 24 horas
    ]
]);
