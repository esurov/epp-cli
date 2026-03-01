<?php

return [

    'host' => env('EPP_HOST', 'epp.nic.at'),

    'port' => (int) env('EPP_PORT', 700),

    'username' => env('EPP_USERNAME', ''),

    'password' => env('EPP_PASSWORD', ''),

    'ssl' => (bool) env('EPP_SSL', true),

    'verify_peer' => (bool) env('EPP_VERIFY_PEER', true),

    'allow_self_signed' => (bool) env('EPP_ALLOW_SELF_SIGNED', false),

    'timeout' => (int) env('EPP_TIMEOUT', 10),

    'log_dir' => env('EPP_LOG_DIR', ''),

];
