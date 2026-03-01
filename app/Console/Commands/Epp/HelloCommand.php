<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\eppHelloRequest;

class HelloCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:hello
        {--lang= : Language to verify}
        {--ver= : Version to verify}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Send a hello request to the EPP server';

    public function handle(): int
    {
        return $this->executeEppOperation(function ($connection) {
            $request = new eppHelloRequest;
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: ' . $response->getResultCode());
            } else {
                $this->line('FAILED: ' . $response->getResultCode());
                $this->line('Hello failed: ' . $response->getResultMessage());
            }

            $this->line('Server Name: ' . $response->getServerName());
            $this->line('Server Date: ' . $response->getServerDate());
            $this->line('Languages: ' . implode(', ', $response->getLanguages()));
            $this->line('Services: ' . implode(', ', $response->getServices()));
            $this->line('Extensions: ' . implode(', ', $response->getExtensions()));
            $this->line('Versions: ' . implode(', ', $response->getVersions()));

            $lang = $this->option('lang');
            $ver = $this->option('ver');

            if ($lang && $ver) {
                try {
                    $response->validateServices($lang, $ver);
                    $this->line("Verification: [OK] Language '{$lang}' and Version '{$ver}' are supported by the server!");
                } catch (\Metaregistrar\EPP\eppException $e) {
                    $this->line('Verification: [Failed] ' . $e->getMessage());
                }
            }
        });
    }
}
