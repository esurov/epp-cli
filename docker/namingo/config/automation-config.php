<?php

return [
    'db_type' => 'mysql',
    'db_host' => getenv('DB_HOST') ?: 'mariadb',
    'db_port' => (int) (getenv('DB_PORT') ?: 3306),
    'db_database' => getenv('DB_DATABASE') ?: 'registry',
    'db_username' => getenv('DB_USERNAME') ?: 'namingo',
    'db_password' => getenv('DB_PASSWORD') ?: 'namingo_password',

    'dns_server' => 'bind',
    'ns' => [
        'ns1' => 'ns1.test.example',
        'ns2' => 'ns2.test.example',
    ],

    'dns_soa' => 'hostmaster.test.example',
    'dns_serial' => date('Ymd') . '01',

    'escrow_deposit_path' => '/opt/registry/escrow',
    'escrow_deleteXML' => false,

    'reporting_path' => '/opt/registry/reporting',

    'mailer' => 'phpmailer',
    'mailer_smtp_host' => 'localhost',
    'mailer_smtp_port' => 25,
    'mailer_smtp_username' => '',
    'mailer_smtp_password' => '',
    'mailer_smtp_encryption' => '',
    'mailer_from' => 'registry@test.example',

    'admin_email' => 'admin@test.example',

    'grace_period' => 30,
    'auto_renew_period' => 45,
    'redemption_period' => 30,
    'pending_delete' => 5,
];
