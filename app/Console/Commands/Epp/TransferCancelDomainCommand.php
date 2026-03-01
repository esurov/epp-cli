<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\atEppDomain;
use Metaregistrar\EPP\atEppTransferRequest;

use function Laravel\Prompts\text;

class TransferCancelDomainCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:transfer-cancel-domain
        {--domain= : Domain name to cancel transfer for}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Cancel a domain transfer';

    public function handle(): int
    {
        $domain = $this->option('domain') ?? text('Enter the domain name:', required: true);

        return $this->executeEppOperation(function ($connection) use ($domain) {
            $request = new atEppTransferRequest(atEppTransferRequest::OPERATION_CANCEL, new atEppDomain($domain));
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Domain transfer cancellation failed: ' . $response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
