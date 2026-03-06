<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppContactHandle;
use Metaregistrar\EPP\eppCheckContactRequest;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\text;

class CheckContactCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('contact:check')
            ->setDescription('Check contact handle availability')
            ->addOption('id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Contact handle(s) to check')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $ids = $this->askIfMissing('id', fn () => [text('Enter a contact handle to check:', required: true)]);

        $this->printCliEquivalent();

        return $this->executeEppOperation(function ($connection) use ($ids) {
            $handles = array_map(fn ($id) => new atEppContactHandle($id), $ids);

            $request = new eppCheckContactRequest($handles);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: '.$response->getResultCode());

                foreach ($response->getCheckedContacts() as $id => $available) {
                    $status = $available ? 'AVAILABLE' : 'IN USE';
                    $this->line("ATTR: {$id} {$status}");
                }
            } else {
                $this->line('FAILED: '.$response->getResultCode());
                $this->line('Contact check failed: '.$response->getResultMessage());
            }

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
