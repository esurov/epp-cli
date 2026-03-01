<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\atEppContactHandle;
use Metaregistrar\EPP\atEppCreateDomainRequest;
use Metaregistrar\EPP\atEppDomain;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateDomainCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:create-domain
        {--domain= : Domain name to create}
        {--nameserver=* : Nameserver (format: ns/ip/ip)}
        {--registrant= : Registrant contact handle}
        {--techc=* : Tech contact handle(s)}
        {--authinfo= : Authorization info}
        {--secdns=* : DNSSEC data (format: keyTag=>..., alg=>..., digestType=>..., digest=>...)}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Create a new domain';

    public function handle(): int
    {
        $domain = $this->option('domain') ?? text('Enter the domain name:', required: true);
        $nameservers = $this->option('nameserver');
        if (empty($nameservers)) {
            $ns = text('Enter nameserver (format: ns.example.com or ns.example.com/1.2.3.4):', required: true);
            $nameservers = [$ns];
        }
        $registrant = $this->option('registrant') ?? text('Enter registrant contact handle:', required: true);
        $techContacts = $this->option('techc');
        if (empty($techContacts)) {
            $tc = text('Enter tech contact handle:', required: true);
            $techContacts = [$tc];
        }
        $authinfo = $this->option('authinfo') ?? password('Enter authorization info:', required: true);
        $secdnsOptions = $this->option('secdns');

        return $this->executeEppOperation(function ($connection) use ($domain, $nameservers, $registrant, $techContacts, $authinfo, $secdnsOptions) {
            $eppDomain = new atEppDomain($domain);
            $eppDomain->setRegistrant(new atEppContactHandle($registrant, 'reg'));

            foreach ($techContacts as $handle) {
                $eppDomain->addContact(new atEppContactHandle($handle, 'tech'));
            }

            $eppDomain->setAuthorisationCode($authinfo);

            foreach ($nameservers as $ns) {
                foreach ($this->parseNameserver($ns) as $host) {
                    $eppDomain->addHost($host);
                }
            }

            foreach ($secdnsOptions as $secdnsStr) {
                if ($secdns = $this->parseSecdns($secdnsStr)) {
                    $eppDomain->addSecdns($secdns);
                }
            }

            $request = new atEppCreateDomainRequest($eppDomain);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Domain create failed: ' . $response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);

            if ($name = $response->getDomainCreated()) {
                $this->line("ATTR: name: $name");
            }
            if ($date = $this->formatDate($response->getDomainCreateDate())) {
                $this->line("ATTR: crDate: {$date}");
            }
        });
    }
}
