<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\atEppContactHandle;
use Metaregistrar\EPP\atEppDomain;
use Metaregistrar\EPP\atEppUndeleteRequest;
use Metaregistrar\EPP\atEppUpdateDomainRequest;
use Metaregistrar\EPP\eppHost;
use Metaregistrar\EPP\eppInfoDomainRequest;
use Metaregistrar\EPP\eppStatus;

use function Laravel\Prompts\text;

class UpdateDomainCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:update-domain
        {--domain= : Domain name to update}
        {--addns=* : Add nameserver (format: ns/ip/ip)}
        {--delns=* : Remove nameserver}
        {--addstatus=* : Add status (format: statusname or statusname/message)}
        {--delstatus=* : Remove status}
        {--registrant= : Change registrant}
        {--addtechc=* : Add tech contact}
        {--deltechc=* : Remove tech contact}
        {--addsecdns=* : Add DNSSEC data}
        {--delsecdns=* : Remove DNSSEC data}
        {--delsecdns-all : Remove all DNSSEC data}
        {--restore : Restore a deleted domain}
        {--authinfo= : Set new authorization info}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Update a domain';

    public function handle(): int
    {
        $domain = $this->option('domain') ?? text('Enter the domain name:', required: true);

        if ($this->option('delsecdns-all') && ! empty($this->option('delsecdns'))) {
            $this->error('Either --delsecdns-all or --delsecdns allowed, not both');

            return self::FAILURE;
        }

        return $this->executeEppOperation(function ($connection) use ($domain) {
            // Handle restore
            if ($this->option('restore')) {
                $request = new atEppUndeleteRequest(new atEppDomain($domain));
                $this->applyCltrid($request, $this->option('cltrid'));

                $response = $connection->request($request);

                if ($response->Success()) {
                    $this->line('SUCCESS: ' . $response->getResultCode());
                } else {
                    $this->line('FAILED: ' . $response->getResultCode());
                    $this->line('Domain restore failed: ' . $response->getResultMessage());
                }

                $this->printConditions($response->getExtensionResult());

                $this->newLine();
                $this->printTransactionIds($response);
            }

            $addns = $this->option('addns');
            $delns = $this->option('delns');
            $addstatus = $this->option('addstatus');
            $delstatus = $this->option('delstatus');
            $registrant = $this->option('registrant');
            $addtechc = $this->option('addtechc');
            $deltechc = $this->option('deltechc');
            $addsecdns = $this->option('addsecdns');
            $delsecdns = $this->option('delsecdns');
            $delallsecdns = $this->option('delsecdns-all');
            $auth = $this->option('authinfo');

            $hasChanges = $addns || $delns || $addstatus || $delstatus || $registrant
                || $addtechc || $deltechc || $addsecdns || $delsecdns || $delallsecdns || $auth;

            if (! $hasChanges) {
                return;
            }

            $chg = new atEppDomain($domain);
            $add = $rem = null;

            if ($registrant) {
                $chg->setRegistrant(new atEppContactHandle($registrant, 'reg'));
            }

            if ($auth) {
                $chg->setAuthorisationCode($auth);
            }

            // Handle nameservers
            foreach ($delns as $ns) {
                if (! $rem) {
                    $rem = new atEppDomain($domain);
                }
                $rem->addHost(new eppHost($ns));
            }

            foreach ($addns as $ns) {
                if (! $add) {
                    $add = new atEppDomain($domain);
                }
                foreach ($this->parseNameserver($ns) as $host) {
                    $add->addHost($host);
                }
            }

            // Handle status
            foreach ($delstatus as $status) {
                if (! $rem) {
                    $rem = new atEppDomain($domain);
                }
                $rem->addStatus(new eppStatus($status));
            }

            foreach ($addstatus as $status) {
                if (! $add) {
                    $add = new atEppDomain($domain);
                }
                $statusInfo = explode('/', $status);
                $add->addStatus(new eppStatus($statusInfo[0], null, $statusInfo[1] ?? null));
            }

            // Handle tech contacts
            foreach ($deltechc as $handle) {
                if (! $rem) {
                    $rem = new atEppDomain($domain);
                }
                $rem->addContact(new atEppContactHandle($handle, 'tech'));
            }

            foreach ($addtechc as $handle) {
                if (! $add) {
                    $add = new atEppDomain($domain);
                }
                $add->addContact(new atEppContactHandle($handle, 'tech'));
            }

            // Handle DNSSEC add
            foreach ($addsecdns as $secdnsStr) {
                if ($secdns = $this->parseSecdns($secdnsStr)) {
                    if (! $add) {
                        $add = new atEppDomain($domain);
                    }
                    $add->addSecdns($secdns);
                }
            }

            // Handle DNSSEC remove
            foreach ($delsecdns as $secdnsStr) {
                if ($secdns = $this->parseSecdns($secdnsStr)) {
                    if (! $rem) {
                        $rem = new atEppDomain($domain);
                    }
                    $rem->addSecdns($secdns);
                }
            }

            // Handle delete all DNSSEC
            if ($delallsecdns) {
                $infoRequest = new eppInfoDomainRequest(new atEppDomain($domain));
                $infoResponse = $connection->request($infoRequest);

                if ($secdnsList = $infoResponse->getKeydata()) {
                    if (! $rem) {
                        $rem = new atEppDomain($domain);
                    }
                    foreach ($secdnsList as $n) {
                        $rem->addSecdns($n);
                    }
                }
            }

            $request = new atEppUpdateDomainRequest($domain, $add, $rem, $chg, true);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Domain update failed: ' . $response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
