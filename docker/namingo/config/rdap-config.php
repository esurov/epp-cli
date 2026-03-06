<?php

return [
    'db_type' => 'mysql',
    'db_host' => getenv('DB_HOST') ?: 'mariadb',
    'db_port' => (int) (getenv('DB_PORT') ?: 3306),
    'db_database' => getenv('DB_DATABASE') ?: 'registry',
    'db_username' => getenv('DB_USERNAME') ?: 'namingo',
    'db_password' => getenv('DB_PASSWORD') ?: 'namingo_password',
    'roid' => 'XX',
    'minimum_data' => false,
    'limited_rdap' => false,
    'registry_url' => 'https://test.example/rdap-terms',
    'rdap_url' => 'http://localhost:7500',
    'rately' => false,
    'limit' => 1000,
    'period' => 60,
];
