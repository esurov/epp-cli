<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use Metaregistrar\EPP\atEppContact;
use Metaregistrar\EPP\atEppContactHandle;
use Metaregistrar\EPP\atEppUpdateContactExtension;
use Metaregistrar\EPP\atEppUpdateContactRequest;
use Metaregistrar\EPP\eppContactPostalInfo;
use Metaregistrar\EPP\eppInfoContactRequest;
use Symfony\Component\Console\Input\InputOption;

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
            ->addOption('voice', null, InputOption::VALUE_REQUIRED, 'Phone number')
            ->addOption('fax', null, InputOption::VALUE_REQUIRED, 'Fax number')
            ->addOption('disclose-phone', null, InputOption::VALUE_REQUIRED, 'Disclose phone in WHOIS (0|1)')
            ->addOption('disclose-fax', null, InputOption::VALUE_REQUIRED, 'Disclose fax in WHOIS (0|1)')
            ->addOption('disclose-email', null, InputOption::VALUE_REQUIRED, 'Disclose email in WHOIS (0|1)')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $id = $this->option('id') ?? text('Enter the contact ID to update:', required: true);

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
                    ? !confirm('Hide email in WHOIS?', default: (bool) $infoResponse->getWhoisHideEmail())
                    : $infoResponse->getWhoisHideEmail());

            $hidePhone = $this->option('disclose-phone') !== null
                ? ($this->option('disclose-phone') == 0)
                : ($interactive
                    ? !confirm('Hide phone in WHOIS?', default: (bool) $infoResponse->getWhoisHidePhone())
                    : $infoResponse->getWhoisHidePhone());

            $hideFax = $this->option('disclose-fax') !== null
                ? ($this->option('disclose-fax') == 0)
                : ($interactive
                    ? !confirm('Hide fax in WHOIS?', default: (bool) $infoResponse->getWhoisHideFax())
                    : $infoResponse->getWhoisHideFax());

            $postalInfo = new eppContactPostalInfo($name, $city, $country, $org, $street, null, $postalcode);
            $contact = new atEppContact($postalInfo, $type, $email, $phone, $fax, $hideEmail, $hidePhone, $hideFax);
            $contact->setDisclose(($hideEmail || $hidePhone || $hideFax) ? 0 : 1);

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
            'type', 'org', 'voice', 'fax', 'disclose-phone', 'disclose-fax', 'disclose-email',
        ];

        foreach ($contactOptions as $option) {
            if ($this->option($option) !== null && $this->option($option) !== []) {
                return false;
            }
        }

        return true;
    }
}
