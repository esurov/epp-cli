<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\eppCheckDomainRequest;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\text;

class CheckDomainCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('epp:check-domain')
            ->setDescription('Check domain name availability')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Domain name(s) to check')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $domains = $this->option('domain');

        if (empty($domains)) {
            $domain = text('Enter a domain name to check:', required: true);
            $domains = [$domain];
        }

        return $this->executeEppOperation(function ($connection) use ($domains) {
            $request = new eppCheckDomainRequest($domains);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());

                foreach ($response->getCheckedDomains() as $checked) {
                    $exists = $checked['available'] ? 'NO' : 'YES';
                    $this->line("ATTR: {$checked['domainname']} {$exists} {$checked['reason']}");
                }
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Domain check failed: ' . $response->getResultMessage());
            }

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
