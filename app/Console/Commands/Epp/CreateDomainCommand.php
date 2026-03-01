<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppContactHandle;
use Metaregistrar\EPP\atEppCreateDomainRequest;
use Metaregistrar\EPP\atEppDomain;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateDomainCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('domain:create')
            ->setDescription('Create a new domain')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain name to create')
            ->addOption('nameserver', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Nameserver (format: ns/ip/ip)')
            ->addOption('registrant', null, InputOption::VALUE_REQUIRED, 'Registrant contact handle')
            ->addOption('techc', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Tech contact handle(s)')
            ->addOption('authinfo', null, InputOption::VALUE_REQUIRED, 'Authorization info')
            ->addOption('secdns', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'DNSSEC data (format: keyTag=>..., alg=>..., digestType=>..., digest=>...)')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
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
