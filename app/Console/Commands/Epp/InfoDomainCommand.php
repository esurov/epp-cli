<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\eppDomain;
use Metaregistrar\EPP\eppInfoDomainRequest;
use Metaregistrar\EPP\eppStatus;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\text;

class InfoDomainCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('epp:info-domain')
            ->setDescription('Get information about a domain')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain name to query')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $domain = $this->option('domain') ?? text('Enter the domain name:', required: true);

        return $this->executeEppOperation(function ($connection) use ($domain) {
            $request = new eppInfoDomainRequest(new eppDomain($domain));
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Domain info failed: ' . $response->getResultMessage());
            }

            if ($name = $response->getDomainName()) {
                $this->line("ATTR: name: $name");
            }
            if ($roid = $response->getDomainRoid()) {
                $this->line("ATTR: roid: $roid");
            }
            if ($clid = $response->getDomainClientId()) {
                $this->line("ATTR: clID: $clid");
            }
            if ($crid = $response->getDomainCreateClientId()) {
                $this->line("ATTR: crID: $crid");
            }
            if ($upid = $response->getDomainUpdateClientId()) {
                $this->line("ATTR: upID: $upid");
            }
            if ($date = $this->formatDate($response->getDomainCreateDate())) {
                $this->line("ATTR: crDate: {$date}");
            }
            if ($date = $this->formatDate($response->getDomainUpdateDate())) {
                $this->line("ATTR: upDate: {$date}");
            }
            if ($date = $this->formatDate($response->getDomainExpirationDate())) {
                $this->line("ATTR: exDate: {$date}");
            }
            if ($auth = $response->getDomainAuthInfo()) {
                $this->line("ATTR: authInfo: $auth");
            }

            foreach ($response->getDomainStatuses() as $status) {
                if ($status instanceof eppStatus) {
                    $statusDesc = $status->getStatusname();
                    if ($statusMessage = $status->getMessage()) {
                        $statusDesc .= " // $statusMessage";
                    }
                    $this->line("ATTR: status: {$statusDesc}");
                } elseif (is_string($status)) {
                    $this->line("ATTR: status: $status");
                }
            }

            $this->newLine();
            $this->line('ATTR: registrant: ' . $response->getDomainRegistrant());
            foreach ($response->getDomainContacts() as $contact) {
                if ($contact->getContactType() == 'tech') {
                    $this->line('ATTR: tech: ' . $contact->getContactHandle());
                }
            }

            if ($ns = $response->getDomainNameservers()) {
                $this->newLine();
                foreach ($ns as $host) {
                    $this->line('ATTR: hostName: ' . $host->getHostname());
                    foreach (($host->getIpAddresses() ?? []) as $ip => $proto) {
                        $this->line("ATTR: hostAddr: {$ip}");
                    }
                }
            }

            $this->newLine();
            if ($secdns = $response->getKeydata()) {
                $this->line('  --- DNSSEC ---');
                foreach ($secdns as $n) {
                    $this->line('ATTR: keyTag: ' . $n->getKeytag());
                    $this->line('ATTR: digestType: ' . $n->getDigestType());
                    $this->line('ATTR: alg: ' . $n->getAlgorithm());
                    $this->line('ATTR: digest: ' . $n->getDigest());
                    $this->newLine();
                }
            }

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
