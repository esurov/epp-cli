<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppContactHandle;
use Metaregistrar\EPP\eppInfoContactRequest;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\text;

class InfoContactCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('contact:info')
            ->setDescription('Get information about a contact')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Contact ID to query')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $id = $this->askIfMissing('id', fn () => text('Enter the contact ID:', required: true));

        $this->printCliEquivalent();

        return $this->executeEppOperation(function ($connection) use ($id) {
            $request = new eppInfoContactRequest(new atEppContactHandle($id));
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: '.$response->getResultCode());
            } else {
                $this->line('FAILED: '.$response->getResultCode());
                $this->line('Contact info failed: '.$response->getResultMessage());
            }

            if ($contactId = $response->getContactId()) {
                $this->line("ATTR: ID: $contactId");
            }
            if ($roid = $response->getContactRoid()) {
                $this->line("ATTR: roid: $roid");
            }
            if ($clid = $response->getContactClientId()) {
                $this->line("ATTR: clID: $clid");
            }
            if ($crid = $response->getContactCreateClientId()) {
                $this->line("ATTR: crID: $crid");
            }
            if ($upid = $response->getContactUpdateClientId()) {
                $this->line("ATTR: upID: $upid");
            }
            if ($date = $this->formatDate($response->getContactCreateDate())) {
                $this->line("ATTR: crDate: {$date}");
            }
            if ($date = $this->formatDate($response->getContactUpdateDate())) {
                $this->line("ATTR: upDate: {$date}");
            }
            foreach ($response->getContactStatus() as $status) {
                $this->line("ATTR: status: $status");
            }

            $contact = $response->getContact();
            for ($i = 0; $i < $contact->getPostalInfoLength(); $i++) {
                $postal = $contact->getPostalInfo($i);
                if ($org = $postal->getOrganisationName()) {
                    $this->line("ATTR: org: $org");
                }
                if ($name = $postal->getName()) {
                    $this->line("ATTR: name: $name");
                }
                for ($j = 0; $j < $postal->getStreetCount(); $j++) {
                    if ($street = $postal->getStreet($j)) {
                        $this->line("ATTR: street: $street");
                    }
                }
                if ($zip = $postal->getZipcode()) {
                    $this->line("ATTR: pc: $zip");
                }
                if ($city = $postal->getCity()) {
                    $this->line("ATTR: city: $city");
                }
                if ($country = $postal->getCountrycode()) {
                    $this->line("ATTR: cc: $country");
                }
            }

            if ($phone = $contact->getVoice()) {
                $this->line("ATTR: voice: $phone");
            }
            $this->line('ATTR: email: '.($contact->getEmail() ?: 'n/a'));
            if ($fax = $contact->getFax()) {
                $this->line("ATTR: fax: $fax");
            }

            $this->line('ATTR: disclose: phone '.(1 - $response->getWhoisHidePhone()));
            $this->line('ATTR: disclose: fax '.(1 - $response->getWhoisHideFax()));
            $this->line('ATTR: disclose: email '.(1 - $response->getWhoisHideEmail()));

            if ($type = $response->getPersonType()) {
                $this->line("ATTR: type: $type");
            }

            if ($verificationStatus = $response->getValidationStatus()) {
                $this->line("ATTR: verification-status: $verificationStatus");
            }
            if ($verificationActionDate = $response->getValidationActionDate()) {
                $this->line("ATTR: verification-action-date: $verificationActionDate");
            }
            if ($verificationReport = $response->getValidationReport()) {
                $this->line('ATTR: verification-report-result: '.$verificationReport->getResult());
                $this->line('ATTR: verification-report-date: '.$verificationReport->getVerificationDate());
                if ($method = $verificationReport->getMethod()) {
                    $this->line("ATTR: verification-report-method: $method");
                }
                if ($reference = $verificationReport->getReference()) {
                    $this->line("ATTR: verification-report-reference: $reference");
                }
                if ($agent = $verificationReport->getAgent()) {
                    $this->line("ATTR: verification-report-agent: $agent");
                }
                if ($receivedDate = $verificationReport->getReceivedDate()) {
                    $this->line("ATTR: verification-report-received-date: $receivedDate");
                }
                if ($clID = $verificationReport->getclID()) {
                    $this->line("ATTR: verification-report-clID: $clID");
                }
            }

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
