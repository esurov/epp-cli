<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\atEppDomain;
use Metaregistrar\EPP\atEppTransferRequest;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class TransferRequestDomainCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:transfer-request-domain
        {--domain= : Domain name to transfer}
        {--authinfo= : Authorization code for the transfer}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Request a domain transfer';

    public function handle(): int
    {
        $domain = $this->option('domain') ?? text('Enter the domain name:', required: true);
        $auth = $this->option('authinfo') ?? password('Enter the authorization code (optional, press Enter to skip):');

        return $this->executeEppOperation(function ($connection) use ($domain, $auth) {
            $eppDomain = new atEppDomain($domain);
            if ($auth) {
                $eppDomain->setAuthorisationCode($auth);
            }

            $request = new atEppTransferRequest(atEppTransferRequest::OPERATION_REQUEST, $eppDomain);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());

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
                    $this->line('ATTR: reDate: ' . $this->formatDate($reDate));
                }
                if ($acID = $response->getTransferActionClientId()) {
                    $this->line("ATTR: acID: $acID");
                }
                if ($acDate = $response->getTransferActionDate()) {
                    $this->line('ATTR: acDate: ' . $this->formatDate($acDate));
                }
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Domain transfer request failed: ' . $response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
