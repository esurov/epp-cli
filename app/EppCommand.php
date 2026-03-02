<?php

namespace App;

use App\Services\EppConnectionService;
use Metaregistrar\EPP\eppException;
use Metaregistrar\EPP\eppHost;
use Metaregistrar\EPP\eppRequest;
use Metaregistrar\EPP\eppSecdns;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class EppCommand extends Command
{
    protected InputInterface $input;

    protected SymfonyStyle $io;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->io = new SymfonyStyle($input, $output);

        return $this->handle();
    }

    abstract protected function handle(): int;

    protected function option(string $name): mixed
    {
        return $this->input->getOption($name);
    }

    protected function line(string $message): void
    {
        $this->io->writeln($message);
    }

    protected function error(string $message): void
    {
        $this->io->error($message);
    }

    protected function newLine(int $count = 1): void
    {
        $this->io->newLine($count);
    }

    /**
     * Execute an EPP operation with automatic connect/disconnect.
     */
    protected function executeEppOperation(callable $operation, ?string $newPassword = null): int
    {
        $service = EppConnectionService::fromConfig();

        try {
            $connection = $service->connect($newPassword, $this->option('logdir'));
            $result = $operation($connection);

            return $result ?? self::SUCCESS;
        } catch (eppException $e) {
            $this->error($e->getMessage());
            $this->printConditions(json_decode($e->getReason() ?? '', true));

            return self::FAILURE;
        } finally {
            $service->disconnect();
        }
    }

    /**
     * Print EPP conditions (messages and details).
     *
     * @param  array<int, array{message?: string, details?: string}>|null  $conditions
     */
    protected function printConditions(?array $conditions): void
    {
        if (! is_array($conditions)) {
            return;
        }

        foreach ($conditions as $condition) {
            if (! empty($condition['message'])) {
                $this->line("Msg: {$condition['message']}");
            }
            if (! empty($condition['details'])) {
                $this->line("Details: {$condition['details']}");
            }
            $this->newLine();
        }
    }

    /**
     * Print client and server transaction IDs.
     */
    protected function printTransactionIds($response): void
    {
        if (method_exists($response, 'getClientTransactionId')) {
            $this->line('ATTR: clTRID: ' . $response->getClientTransactionId());
            $this->line('ATTR: svTRID: ' . $response->getServerTransactionId());
        } else {
            $this->line('ATTR: clTRID: ' . $response->getClTrId());
            $this->line('ATTR: svTRID: ' . $response->getSvTrId());
        }
    }

    /**
     * Apply client transaction ID to a request if provided.
     */
    protected function applyCltrid(eppRequest $request, ?string $cltrid): void
    {
        if (! $cltrid) {
            return;
        }

        if (strlen($cltrid) < 4 || strlen($cltrid) > 64) {
            $this->error('--cltrid must be between 4 and 64 characters');

            return;
        }

        $request->sessionid = $cltrid;
        $request->addSessionId();
    }

    /**
     * Parse a SECDNS string into an eppSecdns object.
     *
     * Format: "keyTag=>'12346', alg=>3, digestType=>1, digest=>'49FD46E6C4B45C55D4DD'"
     */
    protected function parseSecdns(string $str): ?eppSecdns
    {
        $parsed = array_reduce(explode(',', $str), function ($carry, $item) {
            $parts = array_map('trim', explode('=>', $item));
            if (count($parts) === 2) {
                $carry[$parts[0]] = trim($parts[1], "'\"");
            }

            return $carry;
        }, []);

        if (empty($parsed['keyTag']) || empty($parsed['digestType']) || empty($parsed['digest']) || empty($parsed['alg'])) {
            return null;
        }

        $secdns = new eppSecdns;
        $secdns->setKeytag($parsed['keyTag']);
        $secdns->setDigestType($parsed['digestType']);
        $secdns->setDigest($parsed['digest']);
        $secdns->setAlgorithm($parsed['alg']);

        return $secdns;
    }

    /**
     * Parse a nameserver string into eppHost object(s) added to a domain.
     *
     * Format: "ns.example.com" or "ns.example.com/1.2.3.4" or "ns.example.com/1.2.3.4/2001:db8::1"
     *
     * @return eppHost[]
     */
    protected function parseNameserver(string $str): array
    {
        $parts = explode('/', $str);
        $hosts = [];

        if (count($parts) === 1) {
            $hosts[] = new eppHost($parts[0]);
        } else {
            for ($i = 1; $i < count($parts); $i++) {
                if ($parts[$i] && ! filter_var($parts[$i], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ! filter_var($parts[$i], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $this->error($parts[$i] . ' is not a valid IPv4/IPv6 Address');

                    return [];
                }
                $hosts[] = new eppHost($parts[0], $parts[$i]);
            }
        }

        return $hosts;
    }

    /**
     * Format a date string to ISO 8601 if parseable.
     */
    protected function formatDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        if ($time = strtotime($date)) {
            return date('c', $time);
        }

        return $date;
    }
}
