<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\atEppContactHandle;
use Metaregistrar\EPP\atEppDeleteRequest;

use function Laravel\Prompts\text;

class DeleteContactCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:delete-contact
        {--id= : Contact ID to delete}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Delete a contact';

    public function handle(): int
    {
        $id = $this->option('id') ?? text('Enter the contact ID to delete:', required: true);

        return $this->executeEppOperation(function ($connection) use ($id) {
            $request = new atEppDeleteRequest(new atEppContactHandle($id));
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Contact delete failed: ' . $response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
