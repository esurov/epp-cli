<?php

namespace App\Services;

use App\Config;
use Metaregistrar\EPP\atEppConnection;
use Metaregistrar\EPP\eppException;

class EppConnectionService
{
    private ?atEppConnection $connection = null;

    public function __construct(
        private string $hostname,
        private int $port,
        private string $username,
        private string $password,
        private bool $ssl = true,
        private bool $verifyPeer = true,
        private int $timeout = 10,
        private string $logDir = '',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            hostname: Config::get('epp.host'),
            port: Config::get('epp.port'),
            username: Config::get('epp.username'),
            password: Config::get('epp.password'),
            ssl: Config::get('epp.ssl'),
            verifyPeer: Config::get('epp.verify_peer'),
            timeout: Config::get('epp.timeout'),
            logDir: Config::get('epp.log_dir') ?? '',
        );
    }

    public function connect(?string $newPassword = null, ?string $logDir = null): atEppConnection
    {
        $effectiveLogDir = $logDir ?: $this->logDir;
        $logging = (bool) $effectiveLogDir;

        $this->connection = new atEppConnection($logging);

        $hostnamePrefix = $this->ssl ? 'ssl://' : 'tcp://';
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

        set_error_handler(function (int $errno, string $errstr): never {
            throw new eppException("Connection failed: $errstr");
        });

        try {
            $this->connection->connect();
        } catch (eppException $e) {
            $this->connection = null;

            throw $e;
        } finally {
            restore_error_handler();
        }

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
        if (! $this->connection) {
            return;
        }

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
