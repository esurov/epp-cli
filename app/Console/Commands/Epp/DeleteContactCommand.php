<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppContactHandle;
use Metaregistrar\EPP\atEppDeleteRequest;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\text;

class DeleteContactCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('contact:delete')
            ->setDescription('Delete a contact')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Contact ID to delete')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $id = $this->askIfMissing('id', fn () => text('Enter the contact ID to delete:', required: true));

        $this->printCliEquivalent();

        return $this->executeEppOperation(function ($connection) use ($id) {
            $request = new atEppDeleteRequest(new atEppContactHandle($id));
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: '.$response->getResultCode());
            } else {
                $this->line('FAILED: '.$response->getResultCode());
                $this->line('Contact delete failed: '.$response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
