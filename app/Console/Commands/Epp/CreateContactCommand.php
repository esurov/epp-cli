<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppContact;
use Metaregistrar\EPP\atEppCreateContactExtension;
use Metaregistrar\EPP\atEppCreateContactRequest;
use Metaregistrar\EPP\atEppVerificationReport;
use Metaregistrar\EPP\eppContactPostalInfo;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

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
            ->addOption('output-handle-only', null, InputOption::VALUE_NONE, 'Only output the contact handle (for scripting)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $name = $this->askIfMissing('name', fn () => text('Enter contact name:', required: true));

        $street = $this->option('street');
        $interactiveStreet = empty($street);
        if ($interactiveStreet) {
            $street = [text('Enter street address:', required: true)];
        }
        $this->trackOption('street', $street, $interactiveStreet);

        $city = $this->askIfMissing('city', fn () => text('Enter city:', required: true));
        $postalcode = $this->askIfMissing('postalcode', fn () => text('Enter postal code:', required: true));
        $country = $this->askIfMissing('country', fn () => text('Enter country code (e.g. AT):', required: true));
        $email = $this->askIfMissing('email', fn () => text('Enter email address:', required: true));
        $type = $this->askIfMissing('type', fn () => select(
            'Select contact type:',
            ['privateperson' => 'Private Person', 'organisation' => 'Organisation', 'role' => 'Role'],
        ));

        if (! in_array($type, ['privateperson', 'organisation', 'role'])) {
            $this->error('--type must be one of: privateperson, organisation, role');

            return self::FAILURE;
        }

        $org = $this->askIfMissing('org', fn () => text('Organisation name:'));
        $sp = $this->askIfMissing('sp', fn () => text('State/Province:'));
        $phone = $this->askIfMissing('voice', fn () => text('Phone number:'));
        $fax = $this->askIfMissing('fax', fn () => text('Fax number:'));

        $hideEmail = $this->resolveDiscloseFlag('disclose-email')
            ?? ! confirm('Hide email in WHOIS?', default: false);
        $hidePhone = $this->resolveDiscloseFlag('disclose-phone')
            ?? ! confirm('Hide phone in WHOIS?', default: false);
        $hideFax = $this->resolveDiscloseFlag('disclose-fax')
            ?? ! confirm('Hide fax in WHOIS?', default: false);

        $this->printCliEquivalent();

        $pw = $this->option('pw');

        return $this->executeEppOperation(function ($connection) use ($name, $street, $city, $postalcode, $country, $email, $type, $org, $sp, $phone, $fax, $hideEmail, $hidePhone, $hideFax, $pw) {
            $postalInfo = new eppContactPostalInfo($name, $city, $country, $org, $street, $sp ?: null, $postalcode);
            $contact = new atEppContact($postalInfo, $type, $email, $phone, $fax, $hideEmail, $hidePhone, $hideFax);

            if ($pw) {
                $contact->setPassword($pw);
            }

            if ($hideEmail || $hidePhone || $hideFax) {
                $contact->setDisclose(0);
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

            $ext = new atEppCreateContactExtension($contact);
            $request = new atEppCreateContactRequest($contact, $ext);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);
            $handleOnly = $this->option('output-handle-only');

            if ($handleOnly) {
                if ($response->Success() && $id = $response->getContactId()) {
                    $this->line($id);
                }

                return;
            }

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
