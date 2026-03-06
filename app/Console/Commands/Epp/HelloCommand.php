<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\eppHelloRequest;
use Symfony\Component\Console\Input\InputOption;

class HelloCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('server:hello')
            ->setDescription('Send a hello request to the EPP server')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language to verify')
            ->addOption('ver', null, InputOption::VALUE_REQUIRED, 'Version to verify')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        return $this->executeEppOperation(function ($connection) {
            $request = new eppHelloRequest;
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            if ($response->Success()) {
                $this->line('SUCCESS: '.$response->getResultCode());
            } else {
                $this->line('FAILED: '.$response->getResultCode());
                $this->line('Hello failed: '.$response->getResultMessage());
            }

            $this->line('Server Name: '.$response->getServerName());
            $this->line('Server Date: '.$response->getServerDate());
            $this->line('Languages: '.implode(', ', $response->getLanguages()));
            $this->line('Services: '.implode(', ', $response->getServices()));
            $this->line('Extensions: '.implode(', ', $response->getExtensions()));
            $this->line('Versions: '.implode(', ', $response->getVersions()));

            $lang = $this->option('lang');
            $ver = $this->option('ver');

            if ($lang && $ver) {
                try {
                    $response->validateServices($lang, $ver);
                    $this->line("Verification: [OK] Language '{$lang}' and Version '{$ver}' are supported by the server!");
                } catch (\Metaregistrar\EPP\eppException $e) {
                    $this->line('Verification: [Failed] '.$e->getMessage());
                }
            }
        });
    }
}
