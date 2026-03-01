<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppDeleteRequest;
use Metaregistrar\EPP\atEppDomain;
use Metaregistrar\EPP\atEppDomainDeleteExtension;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class DeleteDomainCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('epp:delete-domain')
            ->setDescription('Delete a domain')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain name to delete')
            ->addOption('scheduledate', null, InputOption::VALUE_REQUIRED, 'When to delete (now|expiration)')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $domain = $this->option('domain') ?? text('Enter the domain name to delete:', required: true);

        $scheduledate = $this->option('scheduledate') ?? select(
            'When should the domain be deleted?',
            ['now' => 'Now', 'expiration' => 'At expiration'],
            'now',
        );

        if (! in_array($scheduledate, ['now', 'expiration'])) {
            $this->error('--scheduledate must be "now" or "expiration"');

            return self::FAILURE;
        }

        return $this->executeEppOperation(function ($connection) use ($domain, $scheduledate) {
            $ext = new atEppDomainDeleteExtension(['pure_delete' => 1, 'schedule_date' => $scheduledate]);
            $request = new atEppDeleteRequest(new atEppDomain($domain), $ext);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Domain delete failed: ' . $response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
