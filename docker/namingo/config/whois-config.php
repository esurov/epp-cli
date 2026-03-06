<?php

return [
    'db_type' => 'mysql',
    'db_host' => getenv('DB_HOST') ?: 'mariadb',
    'db_port' => (int) (getenv('DB_PORT') ?: 3306),
    'db_database' => getenv('DB_DATABASE') ?: 'registry',
    'db_username' => getenv('DB_USERNAME') ?: 'namingo',
    'db_password' => getenv('DB_PASSWORD') ?: 'namingo_password',
    'whois_ipv4' => '0.0.0.0',
    'whois_ipv6' => false,
    'privacy' => false,
    'minimum_data' => false,
    'limited_whois' => false,
    'roid' => 'XX',
    'rately' => false,
    'limit' => 25,
    'period' => 60,
];
