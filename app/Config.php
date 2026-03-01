<?php

namespace App;

class Config
{
    /** @var array<string, mixed> */
    private static array $map = [
        'epp.host' => ['EPP_HOST', 'epp.nic.at'],
        'epp.port' => ['EPP_PORT', 700, 'int'],
        'epp.username' => ['EPP_USERNAME', ''],
        'epp.password' => ['EPP_PASSWORD', ''],
        'epp.ssl' => ['EPP_SSL', true, 'bool'],
        'epp.verify_peer' => ['EPP_VERIFY_PEER', true, 'bool'],
        'epp.timeout' => ['EPP_TIMEOUT', 10, 'int'],
        'epp.log_dir' => ['EPP_LOG_DIR', ''],
    ];

    public static function get(string $key): mixed
    {
        if (! isset(self::$map[$key])) {
            return null;
        }

        [$envKey, $default, $cast] = array_pad(self::$map[$key], 3, null);

        $value = $_ENV[$envKey] ?? getenv($envKey) ?: null;

        if ($value === null) {
            return $default;
        }

        return match ($cast) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
