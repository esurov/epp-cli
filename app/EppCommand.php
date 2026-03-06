<?php

namespace App;

use App\Services\EppConnectionService;
use Metaregistrar\EPP\eppException;
use Metaregistrar\EPP\eppHost;
use Metaregistrar\EPP\eppRequest;
use Metaregistrar\EPP\eppSecdns;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class EppCommand extends Command
{
    protected InputInterface $input;

    protected SymfonyStyle $io;

    /** @var array<string, mixed> */
    private array $resolvedOptions = [];

    private bool $hadInteractiveInput = false;

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
            $this->line('ATTR: clTRID: '.$response->getClientTransactionId());
            $this->line('ATTR: svTRID: '.$response->getServerTransactionId());
        } else {
            $this->line('ATTR: clTRID: '.$response->getClTrId());
            $this->line('ATTR: svTRID: '.$response->getSvTrId());
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
                    $this->error($parts[$i].' is not a valid IPv4/IPv6 Address');

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

    /**
     * Resolve an option value, falling back to a prompt if not provided.
     * Tracks whether interactive input was used.
     */
    protected function askIfMissing(string $name, callable $prompt): mixed
    {
        $value = $this->option($name);

        if (is_array($value) ? empty($value) : ($value === null)) {
            $value = $prompt();
            $this->hadInteractiveInput = true;
        }

        $this->resolvedOptions[$name] = $value;

        return $value;
    }

    /**
     * Manually track a resolved option value for CLI equivalent output.
     */
    protected function trackOption(string $name, mixed $value, bool $interactive = false): void
    {
        if ($interactive) {
            $this->hadInteractiveInput = true;
        }

        $this->resolvedOptions[$name] = $value;
    }

    /**
     * Print the equivalent CLI command if any input was gathered interactively.
     */
    protected function printCliEquivalent(): void
    {
        if (! $this->hadInteractiveInput) {
            return;
        }

        $parts = [$this->getName()];

        foreach ($this->resolvedOptions as $name => $value) {
            $this->appendOptionParts($parts, $name, $value);
        }

        $definition = $this->getDefinition();
        foreach ($definition->getOptions() as $option) {
            $name = $option->getName();
            if (isset($this->resolvedOptions[$name])) {
                continue;
            }
            if (in_array($name, ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction'], true)) {
                continue;
            }
            $value = $this->input->getOption($name);
            if ($value === $option->getDefault()) {
                continue;
            }
            $this->appendOptionParts($parts, $name, $value);
        }

        $this->newLine();
        $this->line('CLI: php bin/epp '.implode(' ', $parts));
    }

    private function appendOptionParts(array &$parts, string $name, mixed $value): void
    {
        if ($value === null || $value === '' || $value === false || $value === []) {
            return;
        }

        if ($value === true) {
            $parts[] = '--'.$name;

            return;
        }

        if (is_array($value)) {
            foreach ($value as $v) {
                if ($v !== null && $v !== '') {
                    $parts[] = '--'.$name.'='.$this->escapeOptionValue((string) $v);
                }
            }

            return;
        }

        $parts[] = '--'.$name.'='.$this->escapeOptionValue((string) $value);
    }

    private function escapeOptionValue(string $value): string
    {
        if (preg_match('/[\s\'\"\\\\!$`]/', $value)) {
            return "'".str_replace("'", "'\\''", $value)."'";
        }

        return $value;
    }
}
