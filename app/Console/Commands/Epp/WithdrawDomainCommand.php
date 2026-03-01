<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppDomain;
use Metaregistrar\EPP\atEppWithdrawRequest;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class WithdrawDomainCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('epp:withdraw-domain')
            ->setDescription('Withdraw a domain')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain name to withdraw')
            ->addOption('deletezone', null, InputOption::VALUE_NONE, 'Also delete the zone')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $domain = $this->option('domain') ?? text('Enter the domain name:', required: true);

        $deleteZone = $this->option('deletezone');
        if (! $deleteZone && ! $this->input->getOption('no-interaction')) {
            $deleteZone = confirm('Also delete the zone?', false);
        }

        return $this->executeEppOperation(function ($connection) use ($domain, $deleteZone) {
            $request = new atEppWithdrawRequest(new atEppDomain($domain), $deleteZone);
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
