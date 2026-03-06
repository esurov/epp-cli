<?php

return [
    'db_type' => 'mysql',
    'db_host' => getenv('DB_HOST') ?: 'mariadb',
    'db_port' => (int) (getenv('DB_PORT') ?: 3306),
    'db_database' => getenv('DB_DATABASE') ?: 'registry',
    'db_username' => getenv('DB_USERNAME') ?: 'namingo',
    'db_password' => getenv('DB_PASSWORD') ?: 'namingo_password',
    'das_ipv4' => '0.0.0.0',
    'das_ipv6' => false,
    'rately' => false,
    'limit' => 1000,
    'period' => 60,
];
