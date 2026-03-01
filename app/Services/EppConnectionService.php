<?php

namespace App\Services;

use Metaregistrar\EPP\atEppConnection;
use Metaregistrar\EPP\eppException;

class EppConnectionService
{
    private atEppConnection $connection;

    public function __construct(
        private string $hostname,
        private int $port,
        private string $username,
        private string $password,
        private bool $ssl = true,
        private bool $verifyPeer = true,
        private bool $allowSelfSigned = false,
        private int $timeout = 10,
        private string $logDir = '',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            hostname: config('epp.host'),
            port: config('epp.port'),
            username: config('epp.username'),
            password: config('epp.password'),
            ssl: config('epp.ssl'),
            verifyPeer: config('epp.verify_peer'),
            allowSelfSigned: config('epp.allow_self_signed'),
            timeout: config('epp.timeout'),
            logDir: config('epp.log_dir') ?? '',
        );
    }

    public function connect(?string $newPassword = null, ?string $logDir = null): atEppConnection
    {
        $effectiveLogDir = $logDir ?: $this->logDir;
        $logging = (bool) $effectiveLogDir;

        $this->connection = new atEppConnection($logging);

        $hostnamePrefix = $this->ssl ? 'ssl://' : '';
        $this->connection->setHostname($hostnamePrefix . $this->hostname);
        $this->connection->setPort($this->port);
        $this->connection->setTimeout($this->timeout);

        if (! $this->verifyPeer) {
            $this->connection->setVerifyPeer(false);
        }

        if ($logging) {
            $this->connection->setLogFile(
                rtrim($effectiveLogDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log'
            );
        }

        $this->connection->connect();
        $this->connection->setUsername($this->username);
        $this->connection->setPassword($this->password);

        if ($newPassword !== null) {
            $this->connection->setNewPassword($newPassword);
        }

        $this->connection->login();

        return $this->connection;
    }

    public function disconnect(): void
    {
        try {
            $this->connection->logout();
            $this->connection->disconnect();
        } catch (eppException) {
            // Silently handle disconnect errors
        }
    }

    public function getConnection(): atEppConnection
    {
        return $this->connection;
    }
}
