<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\atEppContact;
use Metaregistrar\EPP\atEppCreateContactExtension;
use Metaregistrar\EPP\atEppCreateContactRequest;
use Metaregistrar\EPP\eppContactPostalInfo;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateContactCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:create-contact
        {--name= : Contact name}
        {--street=* : Street address (can be specified multiple times)}
        {--city= : City}
        {--postalcode= : Postal code}
        {--country= : Country code (ISO 3166-1 alpha-2)}
        {--email= : Email address}
        {--type= : Contact type (privateperson|organisation|role)}
        {--org= : Organisation name}
        {--voice= : Phone number}
        {--fax= : Fax number}
        {--disclose-phone= : Disclose phone in WHOIS (0|1)}
        {--disclose-fax= : Disclose fax in WHOIS (0|1)}
        {--disclose-email= : Disclose email in WHOIS (0|1)}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Create a new contact';

    public function handle(): int
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

        $org = $this->option('org');
        $phone = $this->option('voice');
        $fax = $this->option('fax');

        $hideEmail = $this->resolveDiscloseFlag('disclose-email');
        $hidePhone = $this->resolveDiscloseFlag('disclose-phone');
        $hideFax = $this->resolveDiscloseFlag('disclose-fax');

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
                $this->line('SUCCESS: ' . $response->getResultCode());
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Contact create failed: ' . $response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);

            if ($id = $response->getContactId()) {
                $this->line("ATTR: ID: $id");
            }
        });
    }

    private function resolveDiscloseFlag(string $option): bool
    {
        $rawValue = $this->option($option);

        if ($rawValue !== null) {
            return $rawValue == 0;
        }

        return false;
    }
}
