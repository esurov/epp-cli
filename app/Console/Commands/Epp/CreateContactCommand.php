<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use Metaregistrar\EPP\atEppContact;
use Metaregistrar\EPP\atEppCreateContactExtension;
use Metaregistrar\EPP\atEppCreateContactRequest;
use Metaregistrar\EPP\eppContactPostalInfo;
use Symfony\Component\Console\Input\InputOption;

class CreateContactCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('contact:create')
            ->setDescription('Create a new contact')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Contact name')
            ->addOption('street', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Street address (can be specified multiple times)')
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'City')
            ->addOption('postalcode', null, InputOption::VALUE_REQUIRED, 'Postal code')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country code (ISO 3166-1 alpha-2)')
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
        $name = $this->option('name') ?? text('Enter contact name:', required: true);
        $street = $this->option('street');
        if (empty($street)) {
            $street = [text('Enter street address:', required: true)];
        }
        $city = $this->option('city') ?? text('Enter city:', required: true);
        $postalcode = $this->option('postalcode') ?? text('Enter postal code:', required: true);
        $country = $this->option('country') ?? text('Enter country code (e.g. AT):', required: true);
        $email = $this->option('email') ?? text('Enter email address:', required: true);
        $type = $this->option('type') ?? select(
            'Select contact type:',
            ['privateperson' => 'Private Person', 'organisation' => 'Organisation', 'role' => 'Role'],
        );

        if (! in_array($type, ['privateperson', 'organisation', 'role'])) {
            $this->error('--type must be one of: privateperson, organisation, role');

            return self::FAILURE;
        }

        $org = $this->option('org') ?? text('Organisation name:');
        $phone = $this->option('voice') ?? text('Phone number:');
        $fax = $this->option('fax') ?? text('Fax number:');

        $hideEmail = $this->resolveDiscloseFlag('disclose-email')
            ?? !confirm('Hide email in WHOIS?', default: false);
        $hidePhone = $this->resolveDiscloseFlag('disclose-phone')
            ?? !confirm('Hide phone in WHOIS?', default: false);
        $hideFax = $this->resolveDiscloseFlag('disclose-fax')
            ?? !confirm('Hide fax in WHOIS?', default: false);

        return $this->executeEppOperation(function ($connection) use ($name, $street, $city, $postalcode, $country, $email, $type, $org, $phone, $fax, $hideEmail, $hidePhone, $hideFax) {
            $postalInfo = new eppContactPostalInfo($name, $city, $country, $org, $street, null, $postalcode);
            $contact = new atEppContact($postalInfo, $type, $email, $phone, $fax, $hideEmail, $hidePhone, $hideFax);

            if ($hideEmail || $hidePhone || $hideFax) {
                $contact->setDisclose(0);
            }

            $ext = new atEppCreateContactExtension($contact);
            $request = new atEppCreateContactRequest($contact, $ext);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: '.$response->getResultCode());
            } else {
                $this->line('FAILED: '.$response->getResultCode());
                $this->line('Contact create failed: '.$response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);

            if ($id = $response->getContactId()) {
                $this->line("ATTR: ID: $id");
            }
        });
    }

    private function resolveDiscloseFlag(string $option): ?bool
    {
        $rawValue = $this->option($option);

        if ($rawValue !== null) {
            return $rawValue == 0;
        }

        return false;
    }
}
