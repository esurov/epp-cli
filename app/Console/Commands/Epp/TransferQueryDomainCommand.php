<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppDomain;
use Metaregistrar\EPP\atEppTransferRequest;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\text;

class TransferQueryDomainCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('domain:transfer-query')
            ->setDescription('Query domain transfer status')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain name to query transfer status')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $domain = $this->askIfMissing('domain', fn () => text('Enter the domain name:', required: true));

        $this->printCliEquivalent();

        return $this->executeEppOperation(function ($connection) use ($domain) {
            $request = new atEppTransferRequest(atEppTransferRequest::OPERATION_QUERY, new atEppDomain($domain));
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: '.$response->getResultCode());

                if ($name = $response->getDomainName()) {
                    $this->line("ATTR: name: $name");
                }
                if ($trStatus = $response->getTransferStatus()) {
                    $this->line("ATTR: trStatus: $trStatus");
                }
                if ($reID = $response->getTransferRequestClientId()) {
                    $this->line("ATTR: reID: $reID");
                }
                if ($reDate = $response->getTransferRequestDate()) {
                    $this->line('ATTR: reDate: '.$this->formatDate($reDate));
                }
                if ($acID = $response->getTransferActionClientId()) {
                    $this->line("ATTR: acID: $acID");
                }
                if ($acDate = $response->getTransferActionDate()) {
                    $this->line('ATTR: acDate: '.$this->formatDate($acDate));
                }
            } else {
                $this->line('FAILED: '.$response->getResultCode());
                $this->line('Domain transfer query failed: '.$response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
