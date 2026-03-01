<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\atEppDeleteRequest;
use Metaregistrar\EPP\atEppDomain;
use Metaregistrar\EPP\atEppDomainDeleteExtension;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class DeleteDomainCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:delete-domain
        {--domain= : Domain name to delete}
        {--scheduledate= : When to delete (now|expiration)}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Delete a domain';

    public function handle(): int
    {
        $domain = $this->option('domain') ?? text('Enter the domain name to delete:', required: true);

        $scheduledate = $this->option('scheduledate') ?? select(
            'When should the domain be deleted?',
            ['now' => 'Now', 'expiration' => 'At expiration'],
            'now',
        );

        if (! in_array($scheduledate, ['now', 'expiration'])) {
            $this->error('--scheduledate must be "now" or "expiration"');

            return self::FAILURE;
        }

        return $this->executeEppOperation(function ($connection) use ($domain, $scheduledate) {
            $ext = new atEppDomainDeleteExtension(['pure_delete' => 1, 'schedule_date' => $scheduledate]);
            $request = new atEppDeleteRequest(new atEppDomain($domain), $ext);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Domain delete failed: ' . $response->getResultMessage());
            }

            $this->printConditions($response->getExtensionResult());

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }
}
