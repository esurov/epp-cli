<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\eppCheckDomainRequest;

use function Laravel\Prompts\text;

class CheckDomainCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:check-domain
        {--domain=* : Domain name(s) to check}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Check domain name availability';

    public function handle(): int
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
