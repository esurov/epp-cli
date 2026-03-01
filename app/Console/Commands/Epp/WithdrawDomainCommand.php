<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\atEppDomain;
use Metaregistrar\EPP\atEppWithdrawRequest;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class WithdrawDomainCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:withdraw-domain
        {--domain= : Domain name to withdraw}
        {--deletezone : Also delete the zone}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Withdraw a domain';

    public function handle(): int
    {
        $domain = $this->option('domain') ?? text('Enter the domain name:', required: true);

        $deleteZone = $this->option('deletezone');
        if (! $deleteZone && ! $this->option('no-interaction')) {
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
