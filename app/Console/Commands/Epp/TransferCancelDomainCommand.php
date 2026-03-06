<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppDomain;
use Metaregistrar\EPP\atEppTransferRequest;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\text;

class TransferCancelDomainCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('domain:transfer-cancel')
            ->setDescription('Cancel a domain transfer')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain name to cancel transfer for')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $domain = $this->askIfMissing('domain', fn () => text('Enter the domain name:', required: true));

        $this->printCliEquivalent();

        return $this->executeEppOperation(function ($connection) use ($domain) {
            $request = new atEppTransferRequest(atEppTransferRequest::OPERATION_CANCEL, new atEppDomain($domain));
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: '.$response->getResultCode());
            } else {
                $this->line('FAILED: '.$response->getResultCode());
                $this->line('Domain transfer cancellation failed: '.$response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
