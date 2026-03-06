<?php

return [
    'db_type' => 'mysql',
    'db_host' => getenv('DB_HOST') ?: 'mariadb',
    'db_port' => (int) (getenv('DB_PORT') ?: 3306),
    'db_database' => getenv('DB_DATABASE') ?: 'registry',
    'db_username' => getenv('DB_USERNAME') ?: 'namingo',
    'db_password' => getenv('DB_PASSWORD') ?: 'namingo_password',
    'epp_host' => '0.0.0.0',
    'epp_port' => 700,
    'epp_pid' => '/var/run/epp.pid',
    'epp_greeting' => 'Namingo EPP Server 1.0',
    'epp_prefix' => 'namingo',
    'ssl_cert' => '/opt/registry/epp/epp.crt',
    'ssl_key' => '/opt/registry/epp/epp.key',
    'test_tlds' => '.test,.com.test',
    'rately' => false,
    'limit' => 1000,
    'period' => 60,
    'minimum_data' => false,
    'ns_mode' => 'hostObj',
    'epp_max_frame' => 4 * 1024 * 1024,
];
