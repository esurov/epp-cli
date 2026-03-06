<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppContact;
use Metaregistrar\EPP\atEppContactHandle;
use Metaregistrar\EPP\atEppUpdateContactExtension;
use Metaregistrar\EPP\atEppUpdateContactRequest;
use Metaregistrar\EPP\atEppVerificationReport;
use Metaregistrar\EPP\eppContactPostalInfo;
use Metaregistrar\EPP\eppInfoContactRequest;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class UpdateContactCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('contact:update')
            ->setDescription('Update an existing contact')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Contact ID to update')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Contact name')
            ->addOption('street', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Street address')
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'City')
            ->addOption('postalcode', null, InputOption::VALUE_REQUIRED, 'Postal code')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country code')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Contact type (privateperson|organisation|role)')
            ->addOption('org', null, InputOption::VALUE_REQUIRED, 'Organisation name')
            ->addOption('sp', null, InputOption::VALUE_REQUIRED, 'State/Province')
            ->addOption('voice', null, InputOption::VALUE_REQUIRED, 'Phone number')
            ->addOption('fax', null, InputOption::VALUE_REQUIRED, 'Fax number')
            ->addOption('disclose-phone', null, InputOption::VALUE_REQUIRED, 'Disclose phone in WHOIS (0|1)')
            ->addOption('disclose-fax', null, InputOption::VALUE_REQUIRED, 'Disclose fax in WHOIS (0|1)')
            ->addOption('disclose-email', null, InputOption::VALUE_REQUIRED, 'Disclose email in WHOIS (0|1)')
            ->addOption('pw', null, InputOption::VALUE_REQUIRED, 'Authorization info (password) for the contact')
            ->addOption('verification-report-status', null, InputOption::VALUE_REQUIRED, 'Verification report result (success|failure)')
            ->addOption('verification-report-date', null, InputOption::VALUE_REQUIRED, 'Verification date (ISO 8601, e.g. 2024-01-15T10:30:00Z)')
            ->addOption('verification-report-reference', null, InputOption::VALUE_REQUIRED, 'Verification report reference identifier')
            ->addOption('verification-report-agent', null, InputOption::VALUE_REQUIRED, 'Verification agent name')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $id = $this->askIfMissing('id', fn () => text('Enter the contact ID to update:', required: true));

        $type = $this->option('type');
        if ($type && ! in_array($type, ['privateperson', 'organisation', 'role'])) {
            $this->error('--type must be one of: privateperson, organisation, role');

            return self::FAILURE;
        }

        return $this->executeEppOperation(function ($connection) use ($id) {
            $handle = new atEppContactHandle($id);

            // Fetch existing contact to merge with provided values
            $infoRequest = new eppInfoContactRequest($handle);
            $infoResponse = $connection->request($infoRequest);
            $existingContact = $infoResponse->getContact();
            $existingPostal = $existingContact->getPostalInfo(0);

            $interactive = $this->hasNoContactOptions();

            $name = $this->option('name')
                ?? ($interactive ? text('Contact name:', default: $existingPostal->getName() ?? '', required: true) : $existingPostal->getName());

            $org = $this->option('org')
                ?? ($interactive ? text('Organisation name:', default: $existingPostal->getOrganisationName() ?? '') : $existingPostal->getOrganisationName());

            $street = $this->option('street');
            if (empty($street)) {
                $existingStreets = [];
                for ($i = 0; $i < $existingPostal->getStreetCount(); $i++) {
                    $existingStreets[] = $existingPostal->getStreet($i);
                }

                if ($interactive) {
                    $street = [text('Street address:', default: implode(', ', $existingStreets), required: true)];
                } else {
                    $street = $existingStreets;
                }
            }

            $sp = $this->option('sp')
                ?? ($interactive ? text('State/Province:', default: $existingPostal->getProvince() ?? '') : $existingPostal->getProvince());

            $city = $this->option('city')
                ?? ($interactive ? text('City:', default: $existingPostal->getCity() ?? '', required: true) : $existingPostal->getCity());

            $postalcode = $this->option('postalcode')
                ?? ($interactive ? text('Postal code:', default: $existingPostal->getZipcode() ?? '', required: true) : $existingPostal->getZipcode());

            $country = $this->option('country')
                ?? ($interactive ? text('Country code (e.g. AT):', default: $existingPostal->getCountrycode() ?? '', required: true) : $existingPostal->getCountrycode());

            $existingType = $infoResponse->getPersonType();
            $type = $this->option('type')
                ?? ($interactive ? select(
                    'Contact type:',
                    ['privateperson' => 'Private Person', 'organisation' => 'Organisation', 'role' => 'Role'],
                    $existingType,
                ) : $existingType);

            $email = $this->option('email')
                ?? ($interactive ? text('Email address:', default: $existingContact->getEmail() ?? '', required: true) : $existingContact->getEmail());

            $phone = $this->option('voice')
                ?? ($interactive ? text('Phone number:', default: $existingContact->getVoice() ?? '') : $existingContact->getVoice());

            $fax = $this->option('fax')
                ?? ($interactive ? text('Fax number:', default: $existingContact->getFax() ?? '') : $existingContact->getFax());

            $hideEmail = $this->option('disclose-email') !== null
                ? ($this->option('disclose-email') == 0)
                : ($interactive
                    ? ! confirm('Hide email in WHOIS?', default: (bool) $infoResponse->getWhoisHideEmail())
                    : $infoResponse->getWhoisHideEmail());

            $hidePhone = $this->option('disclose-phone') !== null
                ? ($this->option('disclose-phone') == 0)
                : ($interactive
                    ? ! confirm('Hide phone in WHOIS?', default: (bool) $infoResponse->getWhoisHidePhone())
                    : $infoResponse->getWhoisHidePhone());

            $hideFax = $this->option('disclose-fax') !== null
                ? ($this->option('disclose-fax') == 0)
                : ($interactive
                    ? ! confirm('Hide fax in WHOIS?', default: (bool) $infoResponse->getWhoisHideFax())
                    : $infoResponse->getWhoisHideFax());

            if ($interactive) {
                $this->trackOption('name', $name, true);
                $this->trackOption('org', $org, true);
                $this->trackOption('street', $street, true);
                $this->trackOption('sp', $sp, true);
                $this->trackOption('city', $city, true);
                $this->trackOption('postalcode', $postalcode, true);
                $this->trackOption('country', $country, true);
                $this->trackOption('type', $type, true);
                $this->trackOption('email', $email, true);
                $this->trackOption('voice', $phone, true);
                $this->trackOption('fax', $fax, true);
                $this->trackOption('disclose-email', $hideEmail ? '0' : '1', true);
                $this->trackOption('disclose-phone', $hidePhone ? '0' : '1', true);
                $this->trackOption('disclose-fax', $hideFax ? '0' : '1', true);
                $this->printCliEquivalent();
            } else {
                $this->printCliEquivalent();
            }

            $postalInfo = new eppContactPostalInfo($name, $city, $country, $org, $street, $sp ?: null, $postalcode);
            $contact = new atEppContact($postalInfo, $type, $email, $phone, $fax, $hideEmail, $hidePhone, $hideFax);
            $contact->setDisclose(($hideEmail || $hidePhone || $hideFax) ? 0 : 1);

            $pw = $this->option('pw');
            if ($pw) {
                $contact->setPassword($pw);
            }

            $verificationStatus = $this->option('verification-report-status');
            if ($verificationStatus) {
                if (! in_array($verificationStatus, ['success', 'failure'])) {
                    $this->error('--verification-report-status must be one of: success, failure');

                    return self::FAILURE;
                }

                $verificationDate = $this->option('verification-report-date');
                if (! $verificationDate) {
                    $this->error('--verification-report-date is required when using --verification-report-status');

                    return self::FAILURE;
                }

                $verificationReport = new atEppVerificationReport(
                    $verificationStatus,
                    $verificationDate,
                    null,
                    $this->option('verification-report-reference'),
                    $this->option('verification-report-agent'),
                );
                $contact->setVerificationReport($verificationReport);
            }

            $ext = new atEppUpdateContactExtension($contact);
            $request = new atEppUpdateContactRequest($handle, null, null, $contact, $ext);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: '.$response->getResultCode());
            } else {
                $this->line('FAILED: '.$response->getResultCode());
                $this->line('Contact update failed: '.$response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }

    private function hasNoContactOptions(): bool
    {
        $contactOptions = [
            'name', 'street', 'city', 'postalcode', 'country', 'email',
            'type', 'org', 'sp', 'voice', 'fax', 'disclose-phone', 'disclose-fax', 'disclose-email',
        ];

        foreach ($contactOptions as $option) {
            if ($this->option($option) !== null && $this->option($option) !== []) {
                return false;
            }
        }

        return true;
    }
}
